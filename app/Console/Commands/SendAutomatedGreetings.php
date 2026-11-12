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

        $tz = appTimezone();

        $now = Carbon::now($tz);

        $today = $dateOpt ? Carbon::parse($dateOpt, $tz) : Carbon::today($tz);
        $todayStr = $today->toDateString();

        $currentTime = $timeOpt ? Carbon::parse($timeOpt, $tz)->format('H:i') : $now->format('H:i');

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
            $dispatchedEmails = [];

            // 1. Birthday Greetings
            if ($template->type === 'Birthday') {
                if ($isEmployee) {
                    User::whereNotNull('date_of_birth')
                        ->where('status', 'active')
                        ->whereNotNull('email')
                        ->whereMonth('date_of_birth', $today->month)
                        ->whereDay('date_of_birth', $today->day)
                        ->chunkById(200, function ($users) use ($template, $todayStr, &$queuedCount, &$skippedDuplicates, &$dispatchedEmails) {
                            foreach ($users as $user) {
                                $emailKey = strtolower(trim((string) $user->email));
                                if (isset($dispatchedEmails[$emailKey]) || $this->isAlreadySent($template->id, $user->email, $todayStr)) {
                                    $skippedDuplicates++;
                                    continue;
                                }

                                $dispatchedEmails[$emailKey] = true;
                                SendGreetingEmailJob::dispatch($template->id, 'employee', $user->id, $todayStr);
                                $queuedCount++;
                            }
                        });
                } else {
                    Contact::whereNotNull('dob')
                        ->whereNotNull('email')
                        ->where('email', '!=', '')
                        ->notOptedOutOfAutomatedEmail()
                        ->whereMonth('dob', $today->month)
                        ->whereDay('dob', $today->day)
                        ->chunkById(200, function ($contacts) use ($template, $todayStr, &$queuedCount, &$skippedDuplicates, &$dispatchedEmails) {
                            foreach ($contacts as $contact) {
                                $emailKey = strtolower(trim((string) $contact->email));
                                if (isset($dispatchedEmails[$emailKey]) || $this->isAlreadySent($template->id, $contact->email, $todayStr)) {
                                    $skippedDuplicates++;
                                    continue;
                                }

                                $dispatchedEmails[$emailKey] = true;
                                SendGreetingEmailJob::dispatch($template->id, 'contact', $contact->id, $todayStr);
                                $queuedCount++;
                            }
                        });
                }
            }

            // 2. Holiday Greetings (Annual recurring check: matches Month and Day every year)
            if ($template->type === 'Holiday' && $template->holiday_date) {
                $holidayCarbon = Carbon::parse($template->holiday_date, $tz);

                // If trigger_event is '1 Day Before', send 1 day before the holiday ($today + 1 day matches holiday Month & Day)
                $targetDate = ($template->trigger_event === '1 Day Before')
                    ? $today->copy()->addDay()
                    : $today;

                if ($holidayCarbon->month === $targetDate->month && $holidayCarbon->day === $targetDate->day) {
                    if ($isEmployee) {
                        User::whereNotNull('email')
                            ->where('status', 'active')
                            ->chunkById(200, function ($users) use ($template, $todayStr, &$queuedCount, &$skippedDuplicates, &$dispatchedEmails) {
                                foreach ($users as $user) {
                                    $emailKey = strtolower(trim((string) $user->email));
                                    if (isset($dispatchedEmails[$emailKey]) || $this->isAlreadySent($template->id, $user->email, $todayStr)) {
                                        $skippedDuplicates++;
                                        continue;
                                    }

                                    $dispatchedEmails[$emailKey] = true;
                                    SendGreetingEmailJob::dispatch($template->id, 'employee', $user->id, $todayStr);
                                    $queuedCount++;
                                }
                            });
                    } else {
                        Contact::whereNotNull('email')
                            ->where('email', '!=', '')
                            ->notOptedOutOfAutomatedEmail()
                            ->chunkById(200, function ($contacts) use ($template, $todayStr, &$queuedCount, &$skippedDuplicates, &$dispatchedEmails) {
                                foreach ($contacts as $contact) {
                                    $emailKey = strtolower(trim((string) $contact->email));
                                    if (isset($dispatchedEmails[$emailKey]) || $this->isAlreadySent($template->id, $contact->email, $todayStr)) {
                                        $skippedDuplicates++;
                                        continue;
                                    }

                                    $dispatchedEmails[$emailKey] = true;
                                    SendGreetingEmailJob::dispatch($template->id, 'contact', $contact->id, $todayStr);
                                    $queuedCount++;
                                }
                            });
                    }
                }
            }

            // 3. Work Anniversary Greetings (Annual recurring check: matches Month and Day of joining_date for active employees)
            if ($template->type === 'Work Anniversary') {
                $hasActiveMilestoneTemplate = $activeTemplates->contains(function ($t) {
                    return $t->type === '5 Year Milestone';
                });

                User::whereNotNull('joining_date')
                    ->where('status', 'active')
                    ->whereNotNull('email')
                    ->whereMonth('joining_date', $today->month)
                    ->whereDay('joining_date', $today->day)
                    ->chunkById(200, function ($users) use ($template, $today, $todayStr, $hasActiveMilestoneTemplate, &$queuedCount, &$skippedDuplicates, &$dispatchedEmails) {
                        foreach ($users as $user) {
                            $joiningDate = Carbon::parse($user->joining_date);
                            $yearsCompleted = $joiningDate->diffInYears($today);

                            // Only celebrate if at least 1 full year completed
                            if ($yearsCompleted < 1) {
                                continue;
                            }

                            // If employee hit exactly 5 years and a dedicated 5-Year Milestone template is active, skip generic work anniversary
                            if ($yearsCompleted === 5 && $hasActiveMilestoneTemplate) {
                                continue;
                            }

                            $emailKey = strtolower(trim((string) $user->email));
                            if (isset($dispatchedEmails[$emailKey]) || $this->isAlreadySent($template->id, $user->email, $todayStr)) {
                                $skippedDuplicates++;
                                continue;
                            }

                            $dispatchedEmails[$emailKey] = true;
                            SendGreetingEmailJob::dispatch($template->id, 'employee', $user->id, $todayStr);
                            $queuedCount++;
                        }
                    });
            }

            // 4. 5 Year Milestone Greetings (Exact 5th year completion from joining_date for active employees)
            if ($template->type === '5 Year Milestone') {
                User::whereNotNull('joining_date')
                    ->where('status', 'active')
                    ->whereNotNull('email')
                    ->whereMonth('joining_date', $today->month)
                    ->whereDay('joining_date', $today->day)
                    ->chunkById(200, function ($users) use ($template, $today, $todayStr, &$queuedCount, &$skippedDuplicates, &$dispatchedEmails) {
                        foreach ($users as $user) {
                            $joiningDate = Carbon::parse($user->joining_date);
                            $yearsCompleted = $joiningDate->diffInYears($today);

                            // Trigger ONLY when exactly 5 years completed
                            if ($yearsCompleted !== 5) {
                                continue;
                            }

                            $emailKey = strtolower(trim((string) $user->email));
                            if (isset($dispatchedEmails[$emailKey]) || $this->isAlreadySent($template->id, $user->email, $todayStr)) {
                                $skippedDuplicates++;
                                continue;
                            }

                            $dispatchedEmails[$emailKey] = true;
                            SendGreetingEmailJob::dispatch($template->id, 'employee', $user->id, $todayStr);
                            $queuedCount++;
                        }
                    });
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
