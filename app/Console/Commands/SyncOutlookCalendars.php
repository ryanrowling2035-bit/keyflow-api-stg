<?php

namespace App\Console\Commands;

use App\Models\OutlookConnection;
use App\Services\OutlookCalendarService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncOutlookCalendars extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'outlook:sync-calendars';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Pulls the latest calendar events from Outlook for all connected users';

    /**
     * Execute the console command.
     */
    public function handle(OutlookCalendarService $calendarService)
    {
        $connections = OutlookConnection::where('status', 'connected')->get();
        $count = $connections->count();

        $this->info("Found {$count} connected Outlook accounts. Starting sync...");

        foreach ($connections as $connection) {
            try {
                $this->info("Syncing calendar for user ID: {$connection->userId}...");
                $result = $calendarService->pullEventsFromOutlook($connection);
                
                if (isset($result['error'])) {
                    $this->error("Failed to sync for user ID {$connection->userId}: {$result['error']}");
                    Log::error("Outlook Calendar Sync Error for User {$connection->userId}", ['error' => $result['error']]);
                } else {
                    $connection->update(['lastCalendarSyncAt' => now()]);
                    $syncedCount = $result['synced'] ?? $result['synced_events_count'] ?? 0;
                    $this->info("Successfully synced for user ID {$connection->userId}. Total imported/updated: {$syncedCount}");
                }
            } catch (\Exception $e) {
                $this->error("Exception while syncing for user ID {$connection->userId}: {$e->getMessage()}");
                Log::error("Outlook Calendar Sync Exception for User {$connection->userId}", ['exception' => $e->getMessage()]);
            }
        }

        $this->info('Calendar sync process completed.');
    }
}

