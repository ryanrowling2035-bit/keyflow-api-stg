<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\GreetingTemplate;
use App\Models\GreetingLog;
use App\Models\Contact;
use App\Models\User;
use App\Jobs\SendGreetingEmailJob;
use Carbon\Carbon;

class SendAutomatedGreetings extends Command
{
    protected $signature = 'greetings:send-automated {--date= : The date to run for (Y-m-d)} {--time= : The time to run for (H:i)}';
    protected $description = 'Scan contacts and employees and queue automated birthday and holiday greetings';

    public function handle()
    {
        $dateOpt = $this->option('date');
        $timeOpt = $this->option('time');

        $now = Carbon::now();

        $today = $dateOpt ? Carbon::parse($dateOpt) : Carbon::today();
        $todayStr = $today->toDateString();

        $currentTime = $timeOpt ? Carbon::parse($timeOpt)->format('H:i') : $now->format('H:i');

        $activeTemplates = GreetingTemplate::where('status', 'Active')->get();

        // Filter templates to only those matching the current time (H:i)
        $activeTemplates = $activeTemplates->filter(function ($template) use ($currentTime) {
            if (empty($template->send_time)) {
                return false;
            }
            $sendTime = Carbon::parse($template->send_time)->format('H:i');
            return $sendTime === $currentTime;
        });

        if ($activeTemplates->isEmpty()) {
            $this->info("No active greeting templates scheduled for time: {$currentTime}.");
            return 0;
        }

        $queuedCount = 0;
        $skippedDuplicates = 0;

        foreach ($activeTemplates as $template) {
            $isEmployee = ($template->target_audience === 'Employee');

            // 1. Birthday Greetings
            if ($template->type === 'Birthday') {
                if ($isEmployee) {
                    User::whereNotNull('date_of_birth')
                        ->where('status', 'active')
                        ->whereNotNull('email')
                        ->whereMonth('date_of_birth', $today->month)
                        ->whereDay('date_of_birth', $today->day)
                        ->chunkById(200, function ($users) use ($template, $todayStr, &$queuedCount, &$skippedDuplicates) {
                            foreach ($users as $user) {
                                if ($this->isAlreadySent($template->id, $user->email, $todayStr)) {
                                    $skippedDuplicates++;
                                    continue;
                                }

                                SendGreetingEmailJob::dispatch($template->id, 'employee', $user->id, $todayStr);
                                $queuedCount++;
                            }
                        });
                } else {
                    Contact::whereNotNull('dob')
                        ->whereNotNull('email')
                        ->whereMonth('dob', $today->month)
                        ->whereDay('dob', $today->day)
                        ->chunkById(200, function ($contacts) use ($template, $todayStr, &$queuedCount, &$skippedDuplicates) {
                            foreach ($contacts as $contact) {
                                if ($this->isAlreadySent($template->id, $contact->email, $todayStr)) {
                                    $skippedDuplicates++;
                                    continue;
                                }

                                SendGreetingEmailJob::dispatch($template->id, 'contact', $contact->id, $todayStr);
                                $queuedCount++;
                            }
                        });
                }
            }

            // 2. Holiday Greetings
            if ($template->type === 'Holiday' && $template->holiday_date) {
                if (Carbon::parse($template->holiday_date)->isSameDay($today)) {
                    if ($isEmployee) {
                        User::whereNotNull('email')
                            ->where('status', 'active')
                            ->chunkById(200, function ($users) use ($template, $todayStr, &$queuedCount, &$skippedDuplicates) {
                                foreach ($users as $user) {
                                    if ($this->isAlreadySent($template->id, $user->email, $todayStr)) {
                                        $skippedDuplicates++;
                                        continue;
                                    }

                                    SendGreetingEmailJob::dispatch($template->id, 'employee', $user->id, $todayStr);
                                    $queuedCount++;
                                }
                            });
                    } else {
                        Contact::whereNotNull('email')
                            ->chunkById(200, function ($contacts) use ($template, $todayStr, &$queuedCount, &$skippedDuplicates) {
                                foreach ($contacts as $contact) {
                                    if ($this->isAlreadySent($template->id, $contact->email, $todayStr)) {
                                        $skippedDuplicates++;
                                        continue;
                                    }

                                    SendGreetingEmailJob::dispatch($template->id, 'contact', $contact->id, $todayStr);
                                    $queuedCount++;
                                }
                            });
                    }
                }
            }
        }

        $this->info("Automated greetings process finished. Queued: {$queuedCount}, Duplicates Skipped: {$skippedDuplicates}");
        return 0;
    }

    /**
     * Check if a greeting email has already been successfully sent for this template & email on the given date.
     */
    private function isAlreadySent(int $templateId, string $email, string $dateStr): bool
    {
        return GreetingLog::where('template_id', $templateId)
            ->where('recipient_email', $email)
            ->whereDate('sent_at', $dateStr)
            ->where('status', 'Sent')
            ->exists();
    }
}
