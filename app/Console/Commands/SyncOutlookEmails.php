<?php

namespace App\Console\Commands;

use App\Models\OutlookConnection;
use App\Services\OutlookEmailService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncOutlookEmails extends Command
{
    protected $signature = 'outlook:sync-emails';
    protected $description = 'Sync recent emails from Outlook for all connected users';

    public function handle(OutlookEmailService $emailService)
    {
        $this->info('Starting Outlook Email Sync...');
        $connections = OutlookConnection::whereNotNull('accessToken')
            ->where('status', 'connected')
            ->get();

        foreach ($connections as $connection) {
            $this->info("Syncing emails for user: {$connection->userId} ({$connection->outlookEmail})");
            try {
                $result = $emailService->pullEmailsFromOutlook($connection);

                if (isset($result['error'])) {
                    $this->error("Failed to sync emails for user {$connection->userId}: {$result['error']}");
                    Log::error("Outlook Email Sync Error for User {$connection->userId}", ['error' => $result['error']]);
                    continue;
                }

                $emails = $result['emails'] ?? [];
                if (!empty($emails)) {
                    $emailService->processAndLinkEmails($emails, $connection, $this);
                }

                $connection->update(['lastEmailSyncAt' => now()]);
            } catch (\Exception $e) {
                $this->error("Exception while syncing emails for user {$connection->userId}: {$e->getMessage()}");
                Log::error("Outlook Email Sync Exception for User {$connection->userId}", ['exception' => $e->getMessage()]);
            }
        }
        $this->info('Outlook Email Sync completed.');
    }
}

