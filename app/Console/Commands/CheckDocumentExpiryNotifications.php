<?php

namespace App\Console\Commands;

use App\Services\DocumentExpiryNotificationService;
use Illuminate\Console\Command;

class CheckDocumentExpiryNotifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hr:check-document-expiries';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Daily background process to check document expiries (30, 15, 7, 0 days) and notify Employee, Manager, and HR Admin based on role & team relationships.';

    /**
     * Execute the console command.
     */
    public function handle(DocumentExpiryNotificationService $expiryService): int
    {
        $this->info('Starting HR document expiry checks...');

        $notificationsSent = $expiryService->checkAndDispatchDailyNotifications();

        $this->info("Completed. Total expiry notifications generated: {$notificationsSent}");

        return Command::SUCCESS;
    }
}
