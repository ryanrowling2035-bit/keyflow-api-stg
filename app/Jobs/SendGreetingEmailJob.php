<?php

namespace App\Jobs;

use App\Models\Contact;
use App\Models\User;
use App\Models\GreetingTemplate;
use App\Models\GreetingLog;
use App\Models\ContactNote;
use App\Models\Task;
use App\Models\Enquiry;
use App\Models\UserActivity;
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

    /**
     * Optional ID of the GreetingLog created when the job was dispatched.
     * Initialized to null so older serialized jobs in the queue don't fail.
     */
    public ?int $greetingLogId = null;

    public function __construct(
        public int $templateId,
        public string $recipientType, // 'contact' or 'employee'
        public int $recipientId,
        public string $targetDate, // Y-m-d
        ?int $greetingLogId = null
    ) {
        $this->greetingLogId = $greetingLogId;
        $this->queue = 'default';
    }

    /**
     * Safety check when deserializing older jobs already in the queue.
     */
    public function __wakeup(): void
    {
        if (! isset($this->greetingLogId)) {
            $this->greetingLogId = null;
        }
    }

    public function handle(): void
    {
        $logId = isset($this->greetingLogId) ? $this->greetingLogId : null;
        $log = $logId ? GreetingLog::find($logId) : null;
        $isEmployee = ($this->recipientType === 'employee');

        $template = GreetingTemplate::find($this->templateId);
        if (! $template || $template->status !== 'Active') {
            $msg = ! $template ? "Template #{$this->templateId} not found" : "Template '{$template->name}' is not Active";
            if ($log) {
                $log->update(['status' => 'Failed', 'error_message' => $msg]);
            }
            try {
                logUserActivity([
                    'userId'      => null,
                    'action'      => 'greeting_failed',
                    'module'      => $isEmployee ? Constants::MODULE_USER : Constants::MODULE_CONTACT,
                    'moduleId'    => $this->recipientId,
                    'description' => "Failed to send greeting: {$msg}",
                    'extraData'   => ['error' => $msg, 'status' => 'Failed'],
                ]);
            } catch (Throwable $e) {}
            return;
        }

        if ($isEmployee) {
            $recipient = User::find($this->recipientId);
        } else {
            $recipient = Contact::with(['contactOwnerUser.meta', 'creator.meta'])->find($this->recipientId);
            // If recipient has an email, pick the most recently updated contact record sharing this email
            if ($recipient && ! empty($recipient->email)) {
                $email = trim((string) $recipient->email);
                $latestContact = Contact::where('email', $email)
                    ->notOptedOutOfAutomatedEmail()
                    ->with(['contactOwnerUser.meta', 'creator.meta'])
                    ->latest('updated_at')
                    ->first();
                if ($latestContact) {
                    $recipient = $latestContact;
                }
            }
        }

        if (! $recipient || empty($recipient->email)) {
            $msg = ! $recipient ? "Recipient ({$this->recipientType} #{$this->recipientId}) not found" : "Recipient '{$recipient->name}' has no email address";
            if ($log) {
                $log->update(['status' => 'Failed', 'error_message' => $msg]);
            }
            try {
                logUserActivity([
                    'userId'      => null,
                    'action'      => 'greeting_failed',
                    'module'      => $isEmployee ? Constants::MODULE_USER : Constants::MODULE_CONTACT,
                    'moduleId'    => $this->recipientId,
                    'description' => "Failed to send {$template->type} greeting: {$msg}",
                    'extraData'   => ['error' => $msg, 'status' => 'Failed'],
                ]);
            } catch (Throwable $e) {}
            return;
        }

        // Safety check: skip contacts who have opted out of automated emails
        if (! $isEmployee && $recipient instanceof Contact) {
            if ($recipient->hasOptedOutOfAutomatedEmail()) {
                $msg = "Skipped: Contact '{$recipient->name}' has opted out of automated emails.";
                if ($log) {
                    $log->update(['status' => 'Failed', 'error_message' => $msg]);
                }
                return;
            }
        }

        // Idempotency: prevent sending duplicate greeting for the same template & recipient on the same day
        $alreadySent = GreetingLog::where('template_id', $template->id)
            ->where('recipient_email', $recipient->email)
            ->whereDate('sent_at', $this->targetDate)
            ->where('status', 'Sent')
            ->when($logId, function ($q, $id) {
                $q->where('id', '!=', $id);
            })
            ->exists();

        if ($alreadySent) {
            if ($log) {
                $log->update(['status' => 'Failed', 'error_message' => 'Skipped: Duplicate greeting already sent today.']);
            }
            return;
        }

        $agent = $isEmployee ? null : $this->resolveActiveAgentForContact($recipient);

        $subject = $this->compileVariables($template->subject, $recipient, $agent, false, $isEmployee);
        $body = $this->compileVariables($template->body, $recipient, $agent, true, $isEmployee);

        try {
            // Check whether to send via AWS SES or standard SMTP based on .env config (AWS_GREETING)
            $useAws = (bool) config('mail.aws_greeting', false);

            if ($useAws) {
                $sesKey = config('services.ses.key');
                $sesSecret = config('services.ses.secret');

                if (empty($sesKey) || empty($sesSecret)) {
                    throw new Exception('AWS SES credentials (AWS_SES_ACCESS_KEY_ID / AWS_SES_SECRET_ACCESS_KEY) are missing in .env. Configure them or set AWS_GREETING=false to send via SMTP.');
                }
            }

            $mailable = new GreetingMail($subject, $body);

            // Use dedicated From address if configured, otherwise fallback to default
            $customFromAddress = ($useAws && config('mail.greetings_from.address'))
                ? config('mail.greetings_from.address')
                : config('mail.from.address');
            $customFromName = ($useAws && config('mail.greetings_from.name'))
                ? config('mail.greetings_from.name')
                : config('mail.from.name');

            if ($isEmployee) {
                $senderDisplayName = $customFromName ?: config('mail.from.name', 'Exclusive Links');
            } else {
                $senderDisplayName = $agent ? $agent->name : 'ELREB / Customer Service Team';
            }

            if (! empty($customFromAddress)) {
                $mailable->from($customFromAddress, $senderDisplayName);
            }

            // Route replies to active agent if available
            if (! $isEmployee && $agent && ! empty($agent->email)) {
                $mailable->replyTo($agent->email, $agent->name);
            }

            // Send Email via AWS SES or SMTP
            $mailer = $useAws ? 'ses' : config('mail.default', 'smtp');
            Mail::mailer($mailer)->to($recipient->email)->send($mailable);

            // Optional CRM notification for internal employees
            if ($isEmployee) {
                try {
                    $notifType = match ($template->type) {
                        'Holiday'          => (Constants::NOTIFICATION_TYPES['hr']['employee_holiday'] ?? 'hr_employee_holiday_greeting'),
                        'Work Anniversary' => (Constants::NOTIFICATION_TYPES['hr']['employee_work_anniversary'] ?? 'hr_employee_work_anniversary_greeting'),
                        '5 Year Milestone' => (Constants::NOTIFICATION_TYPES['hr']['employee_milestone_5year'] ?? 'hr_employee_milestone_5year_greeting'),
                        default            => (Constants::NOTIFICATION_TYPES['hr']['employee_birthday'] ?? 'hr_employee_birthday_greeting'),
                    };

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
            if ($log) {
                $log->update([
                    'status'           => 'Sent',
                    'compiled_subject' => $subject,
                    'compiled_body'    => $body,
                    'error_message'    => null,
                    'sent_at'          => Carbon::now(),
                ]);
            } else {
                $log = GreetingLog::create([
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
                    'error_message'    => null,
                    'sent_at'          => Carbon::now(),
                ]);
            }

            // Activity: Greeting sent
            try {
                logUserActivity([
                    'userId'      => null,
                    'action'      => 'greeting_sent',
                    'module'      => $isEmployee ? Constants::MODULE_USER : Constants::MODULE_CONTACT,
                    'moduleId'    => $recipient->id,
                    'description' => "Sent {$template->type} greeting email to " . ($isEmployee ? 'employee' : 'contact') . " {$recipient->name} ({$recipient->email}) using template '{$template->name}'",
                    'extraData'   => [
                        'template_id'     => $template->id,
                        'template_name'   => $template->name,
                        'type'            => $template->type,
                        'recipient_email' => $recipient->email,
                        'subject'         => $subject,
                        'status'          => 'Sent',
                    ],
                ]);
            } catch (Throwable $e) {
                Log::warning('Failed to log user activity for sent greeting: ' . $e->getMessage());
            }

        } catch (Throwable $e) {
            $errorMessage = $e->getMessage();
            Log::error("SendGreetingEmailJob error for template #{$template->id}, {$this->recipientType} #{$this->recipientId}: " . $errorMessage);

            if ($log) {
                $log->update([
                    'status'           => 'Failed',
                    'compiled_subject' => $subject ?? null,
                    'compiled_body'    => $body ?? null,
                    'error_message'    => $errorMessage,
                    'sent_at'          => Carbon::now(),
                ]);
            } else {
                $log = GreetingLog::create([
                    'template_id'      => $template->id,
                    'template_name'    => $template->name,
                    'type'             => $template->type,
                    'contact_id'       => $isEmployee ? null : ($recipient?->id ?? $this->recipientId),
                    'employee_id'      => $isEmployee ? ($recipient?->id ?? $this->recipientId) : null,
                    'recipient_name'   => $recipient?->name ?? '',
                    'recipient_email'  => $recipient?->email ?? '',
                    'status'           => 'Failed',
                    'compiled_subject' => $subject ?? null,
                    'compiled_body'    => $body ?? null,
                    'error_message'    => $errorMessage,
                    'sent_at'          => Carbon::now(),
                ]);
            }

            // Activity: Greeting failed
            try {
                logUserActivity([
                    'userId'      => null,
                    'action'      => 'greeting_failed',
                    'module'      => $isEmployee ? Constants::MODULE_USER : Constants::MODULE_CONTACT,
                    'moduleId'    => $recipient?->id ?? $this->recipientId,
                    'description' => "Failed to send {$template->type} greeting email to " . ($isEmployee ? 'employee' : 'contact') . " " . ($recipient?->name ?? '') . " (" . ($recipient?->email ?? '') . "). Error: " . $errorMessage,
                    'extraData'   => [
                        'template_id'     => $template->id,
                        'template_name'   => $template->name,
                        'type'            => $template->type,
                        'recipient_email' => $recipient?->email ?? '',
                        'error'           => $errorMessage,
                        'status'          => 'Failed',
                        'attempt'         => $this->attempts(),
                    ],
                ]);
            } catch (Throwable $activityEx) {
                Log::warning('Failed to log user activity for failed greeting: ' . $activityEx->getMessage());
            }

            throw $e;
        }
    }

    /**
     * Handle job failure when all attempts have been exhausted.
     */
    public function failed(?Throwable $exception): void
    {
        $error = $exception ? $exception->getMessage() : 'Unknown error';
        Log::error("SendGreetingEmailJob failed permanently for template #{$this->templateId}, {$this->recipientType} #{$this->recipientId}: {$error}");

        $logId = isset($this->greetingLogId) ? $this->greetingLogId : null;
        if ($logId) {
            GreetingLog::where('id', $logId)->update([
                'status'        => 'Failed',
                'error_message' => $error,
                'sent_at'       => Carbon::now(),
            ]);
        }
    }

    /**
     * Replace dynamic placeholders with recipient and agent values.
     */
    private function compileVariables(?string $text, $recipient, $agent = null, bool $isBody = false, bool $isEmployee = false): string
    {
        if (! $text) {
            return '';
        }

        if ($isBody) {
            $text = formatGreetingSignatureBlock($text);
        }

        $recipientName = trim($recipient->name ?? '');
        $nameParts = explode(' ', $recipientName);
        $firstName = array_shift($nameParts) ?: ($isEmployee ? 'Team Member' : 'Valued');
        $lastName = count($nameParts) > 0 ? implode(' ', $nameParts) : ($isEmployee ? '' : 'Client');

        $companyName = DB::table('global_settings')->where('settingKey', '_companyName')->value('settingValue')
            ?? DB::table('global_settings')->where('settingKey', '_websiteTitle')->value('settingValue')
            ?? DB::table('global_settings')->where('settingKey', '_sendFromName')->value('settingValue')
            ?? 'Exclusive Links Real Estate';

        $companyPhone = DB::table('global_settings')->where('settingKey', '_companyPhone')->value('settingValue')
            ?? DB::table('global_settings')->where('settingKey', '_phone')->value('settingValue')
            ?? '';

        $companyEmail = DB::table('global_settings')->where('settingKey', '_companyEmail')->value('settingValue')
            ?? DB::table('global_settings')->where('settingKey', '_sendFromEmail')->value('settingValue')
            ?? config('mail.from.address', '');

        if ($agent) {
            $agentName = $agent->name;
            $agentPhone = $agent->phone ?? '';
            $agentEmail = $agent->email ?? '';

            $agentBrn = '';
            if ($agent->agentCode) {
                $agentBrn = $agent->agentCode;
            } elseif ($agent->meta) {
                $agentBrn = $agent->meta->firstWhere('metaKey', '_brokerId')?->metaValue
                    ?? $agent->meta->firstWhere('metaKey', '_licenseNumber')?->metaValue
                    ?? '';
            }

            if ($agent->profilePhoto) {
                $photoUrl = getImageUrl($agent->profilePhoto);
                $photoHtml = '<img src="' . $photoUrl . '" alt="Agent" style="width:100px; height:110px; object-fit:cover; border-radius:4px; display:block;" />';
            } else {
                $photoHtml = '<img src="https://ui-avatars.com/api/?name=' . urlencode($agentName) . '" alt="Agent" style="width:100px; height:110px; object-fit:cover; border-radius:4px; display:block;" />';
            }

            $signatureHtml = '<table cellpadding="0" cellspacing="0" border="0" style="margin-top:15px; font-family: Arial, sans-serif;">
                <tr>
                    <td style="padding-right: 15px; vertical-align: top;">' . $photoHtml . '</td>
                    <td style="vertical-align: top; font-family: Arial, sans-serif; font-size: 14px; line-height: 1.5; color: #222222;">
                        <div style="font-weight: bold; font-size: 15px; color: #111111;">' . htmlspecialchars($agentName) . '</div>
                        ' . ($agentPhone ? '<div style="color: #333333; margin-top: 2px;">' . htmlspecialchars($agentPhone) . '</div>' : '') . '
                        ' . ($agentEmail ? '<div style="margin-top: 2px;"><a href="mailto:' . htmlspecialchars($agentEmail) . '" style="color: #0044cc; text-decoration: underline;">' . htmlspecialchars($agentEmail) . '</a></div>' : '') . '
                        ' . ($agentBrn ? '<div style="margin-top: 10px; font-size: 13px; color: #333333;">BRN #: ' . htmlspecialchars($agentBrn) . '</div>' : '') . '
                    </td>
                </tr>
            </table>';
        } else {
            // Ex-agent or no active agent associated: fallback to ELREB / Customer Service Team
            $agentName = $isEmployee ? 'HR Team' : 'ELREB / Customer Service Team';
            $agentPhone = $companyPhone;
            $agentEmail = $companyEmail;
            $agentBrn = '';

            $appLogo = DB::table('global_settings')->where('settingKey', '_appLogo')->value('settingValue');
            if ($appLogo) {
                $photoUrl = getImageUrl($appLogo);
                $photoHtml = '<img src="' . $photoUrl . '" alt="ELREB" style="max-width:120px; max-height:60px; object-fit:contain; display:block;" />';
            } else {
                $photoHtml = '<img src="https://ui-avatars.com/api/?name=ELREB&background=0D8ABC&color=fff&size=128" alt="ELREB" style="width:60px; height:60px; object-fit:cover; border-radius:4px; display:block;" />';
            }

            $signatureHtml = '<table cellpadding="0" cellspacing="0" border="0" style="margin-top:15px; font-family: Arial, sans-serif;">
                <tr>
                    <td style="padding-right: 15px; vertical-align: middle;">' . $photoHtml . '</td>
                    <td style="vertical-align: middle; font-family: Arial, sans-serif; font-size: 14px; line-height: 1.5; color: #222222;">
                        <div style="font-weight: bold; font-size: 15px; color: #111111;">' . htmlspecialchars($agentName) . '</div>
                        ' . ($agentPhone ? '<div style="color: #333333; margin-top: 2px;">' . htmlspecialchars($agentPhone) . '</div>' : '') . '
                        ' . ($agentEmail ? '<div style="margin-top: 2px;"><a href="mailto:' . htmlspecialchars($agentEmail) . '" style="color: #0044cc; text-decoration: underline;">' . htmlspecialchars($agentEmail) . '</a></div>' : '') . '
                    </td>
                </tr>
            </table>';
        }

        $role = $isEmployee ? ($recipient->designation ?? '') : '';
        $department = $isEmployee ? ($recipient->department ?? '') : '';

        $yearsCompleted = '';
        $joiningDateFormatted = '';
        if ($isEmployee && ! empty($recipient->joining_date)) {
            $jDate = Carbon::parse($recipient->joining_date);
            $targetDt = Carbon::parse($this->targetDate);
            $diffYears = $jDate->diffInYears($targetDt);
            $yearsCompleted = $diffYears . ' ' . ($diffYears === 1 ? 'Year' : 'Years');
            $joiningDateFormatted = $jDate->format('d M Y');
        }

        $vars = [
            '{Contact_First_Name}' => $firstName,
            '{Contact_Last_Name}' => $lastName,
            '{Employee_First_Name}' => $firstName,
            '{Employee_Last_Name}' => $lastName,
            '{Employee_Name}' => $recipient->name ?? $firstName,
            '{Employee_Role}' => $role,
            '{Employee_Department}' => $department,
            '{Years_Completed}' => $yearsCompleted,
            '{Work_Anniversary_Years}' => $yearsCompleted,
            '{Joining_Date}' => $joiningDateFormatted,
            '{Company_Name}' => $companyName,
            '{Agent_Name}' => $agentName,
            '{Agent_Phone}' => $agentPhone,
            '{Agent_Email}' => $agentEmail,
            '{Agent_BRN}' => $agentBrn,
            '{Agent_Photo}' => $photoHtml,
            '{Agent_Signature}' => $signatureHtml,
            '{Agent_Card}' => $signatureHtml,
        ];

        foreach ($vars as $key => $value) {
            $text = str_replace($key, $value, $text);
        }

        return $text;
    }

    /**
     * Resolve the active agent who performed the latest activity on this contact/email.
     * 1. Finds all contact records sharing this recipient's email (handles duplicate contacts).
     * 2. Compares the most recent activities performed by ACTIVE agents across:
     *    - ContactNote (calls, notes, emails, meetings logged by agents)
     *    - Task on Contact / Enquiry (tasks created or updated by/for agents)
     *    - Enquiry (latest updated lead/deal assigned to an active agent)
     *    - UserActivity (direct CRM actions on contact/enquiry)
     * 3. Selects the active agent with the newest timestamp across all activities.
     * 4. Fallback: latest enquiry agent -> contactOwnerUser -> creator -> null (Company default).
     */
    protected function resolveActiveAgentForContact(Contact $contact): ?User
    {
        $email = trim((string) $contact->email);

        // 1. Get all contact IDs sharing this email (including the current contact)
        $allContactIds = [$contact->id];
        if (! empty($email)) {
            $allContactIds = Contact::where('email', $email)
                ->pluck('id')
                ->all();
            if (! in_array($contact->id, $allContactIds, true)) {
                $allContactIds[] = $contact->id;
            }
        }

        // Enquiries linked to any of these contact IDs
        $enquiryIds = Enquiry::whereIn('contactId', $allContactIds)->pluck('id')->all();

        // Scope closure: user must be active
        $activeUserScope = function ($q) {
            $q->where(function ($sub) {
                $sub->whereRaw('LOWER(status) = ?', ['active'])
                    ->orWhere('status', '1');
            });
        };

        $candidates = [];

        // Activity 1: ContactNote (Calls, meetings, notes, logged interactions)
        $latestNote = ContactNote::whereIn('contactId', $allContactIds)
            ->whereNotNull('createdBy')
            ->whereHas('creator', $activeUserScope)
            ->latest('created_at')
            ->first(['createdBy', 'created_at']);

        if ($latestNote && $latestNote->created_at) {
            $candidates[] = [
                'userId'    => (int) $latestNote->createdBy,
                'timestamp' => Carbon::parse($latestNote->created_at),
                'source'    => 'contact_note',
            ];
        }

        // Activity 2: Task on Contact (Updated/created follow-up tasks)
        $latestContactTask = Task::where('module', Constants::MODULE_CONTACT)
            ->whereIn('moduleId', $allContactIds)
            ->whereNotNull('assignedTo')
            ->whereHas('assignedUser', $activeUserScope)
            ->latest('updated_at')
            ->first(['assignedTo', 'updated_at']);

        if ($latestContactTask && $latestContactTask->updated_at) {
            $candidates[] = [
                'userId'    => (int) $latestContactTask->assignedTo,
                'timestamp' => Carbon::parse($latestContactTask->updated_at),
                'source'    => 'contact_task',
            ];
        }

        // Activity 3: Task on Enquiry
        if (! empty($enquiryIds)) {
            $latestEnquiryTask = Task::where('module', Constants::MODULE_ENQUIRY)
                ->whereIn('moduleId', $enquiryIds)
                ->whereNotNull('assignedTo')
                ->whereHas('assignedUser', $activeUserScope)
                ->latest('updated_at')
                ->first(['assignedTo', 'updated_at']);

            if ($latestEnquiryTask && $latestEnquiryTask->updated_at) {
                $candidates[] = [
                    'userId'    => (int) $latestEnquiryTask->assignedTo,
                    'timestamp' => Carbon::parse($latestEnquiryTask->updated_at),
                    'source'    => 'enquiry_task',
                ];
            }
        }

        // Activity 4: Enquiry (Agent assigned to the latest updated enquiry)
        $latestEnquiry = Enquiry::whereIn('contactId', $allContactIds)
            ->whereNotNull('agentId')
            ->whereHas('agent', $activeUserScope)
            ->latest('updated_at')
            ->first(['agentId', 'updated_at']);

        if ($latestEnquiry && $latestEnquiry->updated_at) {
            $candidates[] = [
                'userId'    => (int) $latestEnquiry->agentId,
                'timestamp' => Carbon::parse($latestEnquiry->updated_at),
                'source'    => 'enquiry',
            ];
        }

        // Activity 5: UserActivity (Audit actions logged by agent on contact/enquiry)
        $latestUserActivity = UserActivity::where(function ($q) use ($allContactIds, $enquiryIds) {
                $q->where(function ($sub) use ($allContactIds) {
                    $sub->where('module', Constants::MODULE_CONTACT)
                        ->whereIn('moduleId', $allContactIds);
                });
                if (! empty($enquiryIds)) {
                    $q->orWhere(function ($sub) use ($enquiryIds) {
                        $sub->where('module', Constants::MODULE_ENQUIRY)
                            ->whereIn('moduleId', $enquiryIds);
                    });
                }
            })
            ->whereNotNull('userId')
            ->whereHas('user', $activeUserScope)
            ->latest('created_at')
            ->first(['userId', 'created_at']);

        if ($latestUserActivity && $latestUserActivity->created_at) {
            $candidates[] = [
                'userId'    => (int) $latestUserActivity->userId,
                'timestamp' => Carbon::parse($latestUserActivity->created_at),
                'source'    => 'user_activity',
            ];
        }

        // Sort candidates by newest timestamp descending
        if (! empty($candidates)) {
            usort($candidates, function ($a, $b) {
                return $b['timestamp']->getTimestamp() <=> $a['timestamp']->getTimestamp();
            });

            foreach ($candidates as $candidate) {
                $agent = User::with('meta')->find($candidate['userId']);
                if ($agent && $this->isUserActive($agent)) {
                    return $agent;
                }
            }
        }

        // Fallback 1: Check contact's enquiries (latest enquiry with an active agent by ID)
        $latestEnquiryWithActiveAgent = Enquiry::whereIn('contactId', $allContactIds)
            ->whereNotNull('agentId')
            ->whereHas('agent', $activeUserScope)
            ->latest('id')
            ->with(['agent.meta'])
            ->first();

        if ($latestEnquiryWithActiveAgent && $latestEnquiryWithActiveAgent->agent) {
            return $latestEnquiryWithActiveAgent->agent;
        }

        // Fallback 2: Check contactOwnerUser (across all matching contact records)
        $allContacts = Contact::whereIn('id', $allContactIds)->with(['contactOwnerUser.meta', 'creator.meta'])->get();
        foreach ($allContacts as $c) {
            if ($c->contactOwnerUser && $this->isUserActive($c->contactOwnerUser)) {
                return $c->contactOwnerUser;
            }
        }

        // Fallback 3: Check creator (across all matching contact records)
        foreach ($allContacts as $c) {
            if ($c->creator && $this->isUserActive($c->creator)) {
                return $c->creator;
            }
        }

        return null;
    }

    /**
     * Check if a user is active.
     */
    protected function isUserActive(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        $status = strtolower(trim((string) $user->status));
        return in_array($status, ['active', '1', 'true'], true);
    }
}

