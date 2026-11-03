<?php

namespace App\Jobs;

use App\Models\Contact;
use App\Models\User;
use App\Models\GreetingTemplate;
use App\Models\GreetingLog;
use App\Mail\GreetingMail;
use App\Constants\Constants;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;
use Exception;

class SendGreetingEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 60;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 30;

    public function __construct(
        public int $templateId,
        public string $recipientType, // 'contact' or 'employee'
        public int $recipientId,
        public string $targetDate // Y-m-d
    ) {
        $this->onQueue('greetings');
    }

    public function handle(): void
    {
        $template = GreetingTemplate::find($this->templateId);
        if (! $template || $template->status !== 'Active') {
            return;
        }

        $isEmployee = ($this->recipientType === 'employee');

        if ($isEmployee) {
            $recipient = User::find($this->recipientId);
        } else {
            $recipient = Contact::with(['contactOwnerUser.meta', 'creator.meta'])->find($this->recipientId);
        }

        if (! $recipient || empty($recipient->email)) {
            return;
        }

        // Idempotency: prevent sending duplicate greeting for the same template & recipient on the same day
        $alreadySent = GreetingLog::where('template_id', $template->id)
            ->where('recipient_email', $recipient->email)
            ->whereDate('sent_at', $this->targetDate)
            ->where('status', 'Sent')
            ->exists();

        if ($alreadySent) {
            return;
        }

        $agent = $isEmployee ? null : ($recipient->contactOwnerUser ?? $recipient->creator);

        $subject = $this->compileVariables($template->subject, $recipient, $agent, false, $isEmployee);
        $body = $this->compileVariables($template->body, $recipient, $agent, true, $isEmployee);

        try {
            // Strictly enforce AWS SES for greeting emails - NEVER fallback to SMTP
            $sesKey = config('services.ses.key');
            $sesSecret = config('services.ses.secret');

            if (empty($sesKey) || empty($sesSecret)) {
                throw new Exception('AWS SES credentials (AWS_SES_ACCESS_KEY_ID / AWS_SES_SECRET_ACCESS_KEY) are missing in .env. Greeting emails are strictly restricted to AWS SES only and cannot use SMTP.');
            }

            $mailable = new GreetingMail($subject, $body);

            // Use dedicated From address for greetings/SES if provided
            $customFromAddress = config('mail.greetings_from.address') ?: config('mail.from.address');
            $customFromName = config('mail.greetings_from.name') ?? config('mail.from.name');

            if (! empty($customFromAddress)) {
                $mailable->from($customFromAddress, $customFromName);
            }

            // Send Email strictly via AWS SES
            Mail::mailer('ses')->to($recipient->email)->send($mailable);

            // Optional CRM notification for internal employees
            if ($isEmployee) {
                try {
                    $notifType = ($template->type === 'Holiday')
                        ? (Constants::NOTIFICATION_TYPES['hr']['employee_holiday'] ?? 'hr_employee_holiday_greeting')
                        : (Constants::NOTIFICATION_TYPES['hr']['employee_birthday'] ?? 'hr_employee_birthday_greeting');

                    app(\App\Services\NotificationService::class)->notifyUser(
                        $recipient->id,
                        1, // System/Admin actor ID
                        $notifType,
                        $subject,
                        strip_tags($body)
                    );
                } catch (Exception $e) {
                    Log::error('Failed to send CRM notification for employee greeting: ' . $e->getMessage());
                }
            }

            // Record successful greeting delivery
            GreetingLog::create([
                'template_id'      => $template->id,
                'template_name'    => $template->name,
                'type'             => $template->type,
                'contact_id'       => $isEmployee ? null : $recipient->id,
                'employee_id'      => $isEmployee ? $recipient->id : null,
                'recipient_name'   => $recipient->name ?? '',
                'recipient_email'  => $recipient->email,
                'status'           => 'Sent',
                'compiled_subject' => $subject,
                'compiled_body'    => $body,
                'sent_at'          => Carbon::now(),
            ]);
        } catch (Exception $e) {
            // On final attempt, record failed status in GreetingLog
            if ($this->attempts() >= $this->tries) {
                GreetingLog::create([
                    'template_id'      => $template->id,
                    'template_name'    => $template->name,
                    'type'             => $template->type,
                    'contact_id'       => $isEmployee ? null : $recipient->id,
                    'employee_id'      => $isEmployee ? $recipient->id : null,
                    'recipient_name'   => $recipient->name ?? '',
                    'recipient_email'  => $recipient->email,
                    'status'           => 'Failed',
                    'compiled_subject' => $subject,
                    'compiled_body'    => $body,
                    'error_message'    => $e->getMessage(),
                    'sent_at'          => Carbon::now(),
                ]);
            }

            throw $e;
        }
    }

    /**
     * Handle job failure when all attempts have been exhausted.
     */
    public function failed(?Throwable $exception): void
    {
        Log::error("SendGreetingEmailJob failed permanently for template #{$this->templateId}, {$this->recipientType} #{$this->recipientId}: " . ($exception ? $exception->getMessage() : 'Unknown error'));
    }

    /**
     * Replace dynamic placeholders with recipient and agent values.
     */
    private function compileVariables(?string $text, $recipient, $agent = null, bool $isBody = false, bool $isEmployee = false): string
    {
        if (! $text) {
            return '';
        }

        $recipientName = trim($recipient->name ?? '');
        $nameParts = explode(' ', $recipientName);
        $firstName = array_shift($nameParts) ?: ($isEmployee ? 'Team Member' : 'Valued');
        $lastName = count($nameParts) > 0 ? implode(' ', $nameParts) : ($isEmployee ? '' : 'Client');

        $companyName = DB::table('global_settings')->where('settingKey', '_companyName')->value('settingValue')
            ?? config('app.name', 'Exclusive Links');

        $agentName = $agent ? $agent->name : 'Agent';
        $agentPhone = $agent ? $agent->phone : '';
        $agentEmail = $agent ? $agent->email : '';

        $agentBrn = '';
        if ($agent && $agent->agentCode) {
            $agentBrn = $agent->agentCode;
        } elseif ($agent && $agent->meta) {
            $agentBrn = $agent->meta->firstWhere('metaKey', '_licenseNumber')?->metaValue ?? '';
        }

        $photoHtml = '';
        if ($agent && $agent->profilePhoto) {
            $photoHtml = '<img src="' . env('APP_URL') . '/storage/' . $agent->profilePhoto . '" alt="Agent" style="width:90px; height:90px; object-fit:cover; border-radius:8px;" />';
        } else {
            $photoHtml = '<img src="https://ui-avatars.com/api/?name=' . urlencode($agentName) . '" alt="Agent" style="width:90px; height:90px; border-radius:8px;" />';
        }

        $role = $isEmployee ? ($recipient->designation ?? '') : '';
        $department = $isEmployee ? ($recipient->department ?? '') : '';

        $vars = [
            '{Contact_First_Name}' => $firstName,
            '{Contact_Last_Name}' => $lastName,
            '{Employee_First_Name}' => $firstName,
            '{Employee_Role}' => $role,
            '{Employee_Department}' => $department,
            '{Company_Name}' => $companyName,
            '{Agent_Name}' => $agentName,
            '{Agent_Phone}' => $agentPhone,
            '{Agent_Email}' => $agentEmail,
            '{Agent_BRN}' => $agentBrn,
            '{Agent_Photo}' => $photoHtml,
        ];

        foreach ($vars as $key => $value) {
            $text = str_replace($key, $value, $text);
        }

        return $text;
    }
}

