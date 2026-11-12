<?php

namespace App\Services;

use App\Constants\Constants;
use App\Models\CalendarEvent;
use App\Models\GlobalSetting;
use App\Models\OutlookConnection;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use League\OAuth2\Client\Provider\GenericProvider;

class OutlookCalendarService
{
    protected string $graphUrl = 'https://graph.microsoft.com/v1.0';

    /**
     * Get a valid access token, refreshing if expired.
     */
    public function getValidAccessToken(OutlookConnection $connection): ?string
    {
        // If token is still valid (with 5 min buffer), return it
        if ($connection->tokenExpiresAt && $connection->tokenExpiresAt->isAfter(now()->addMinutes(5))) {
            return $connection->accessToken;
        }

        // Token expired — refresh it
        Log::info('Outlook token expired, refreshing...', ['userId' => $connection->userId]);

        return $this->refreshAccessToken($connection);
    }

    /**
     * Refresh the access token using the refresh token.
     */
    protected function refreshAccessToken(OutlookConnection $connection): ?string
    {
        try {
            $provider = $this->getMicrosoftProvider();

            $newToken = $provider->getAccessToken('refresh_token', [
                'refresh_token' => $connection->refreshToken,
                'scope' => 'offline_access Mail.Read User.Read Calendars.ReadWrite',
            ]);

            $newAccessToken = $newToken->getToken();
            $newRefreshToken = $newToken->getRefreshToken();

            $connection->accessToken = $newAccessToken;
            $connection->refreshToken = $newRefreshToken ?? $connection->refreshToken;
            $connection->tokenExpiresAt = now()->addSeconds($newToken->getExpires() - time());
            $connection->save();

            Log::info('Outlook token refreshed successfully', ['userId' => $connection->userId]);

            return $newAccessToken;
        } catch (\Exception $e) {
            Log::error('Outlook token refresh failed', ['error' => $e->getMessage(), 'userId' => $connection->userId]);

            $connection->status = Constants::OUTLOOK_SYNC_STATUS['disconnected'];
            $connection->save();

            return null;
        }
    }

    protected function httpWithToken(string $token)
    {
        return (app()->isLocal() ? Http::withoutVerifying() : Http::getFacadeRoot())
            ->withToken($token);
    }

    /**
     * Build the Microsoft OAuth provider.
     */
    protected function getMicrosoftProvider(): GenericProvider
    {
        $tenantId = env('AZURE_TENANT_ID', 'common');

        $httpClient = new \GuzzleHttp\Client([
            'verify' => !app()->isLocal(),
            'timeout' => 30,
        ]);

        return new GenericProvider([
            'clientId' => env('AZURE_CLIENT_ID'),
            'clientSecret' => env('AZURE_CLIENT_SECRET'),
            'redirectUri' => env('AZURE_REDIRECT_URI'),
            'urlAuthorize' => "https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/authorize",
            'urlAccessToken' => "https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token",
            'urlResourceOwnerDetails' => 'https://graph.microsoft.com/v1.0/me',
            'scopes' => 'offline_access Mail.Read User.Read Calendars.ReadWrite',
            'scopeSeparator' => ' ',
        ], [
            'httpClient' => $httpClient,
        ]);
    }

    // =========================================================================
    //  PUSH: CRM → Outlook
    // =========================================================================

    /**
     * Create an event in Outlook Calendar.
     */
    public function pushEventToOutlook(CalendarEvent $event, OutlookConnection $connection): ?string
    {
        $token = $this->getValidAccessToken($connection);

        if (! $token) {
            return null;
        }

        $body = $this->buildOutlookEventBody($event);

        $response = $this->httpWithToken($token)
            ->post("{$this->graphUrl}/me/events", $body);

        if ($response->successful()) {
            $outlookEventId = $response->json('id');

            $event->update([
                'outlookEventId' => $outlookEventId,
                'isSyncedToOutlook' => true,
            ]);

            return $outlookEventId;
        }

        Log::error('Failed to push event to Outlook', [
            'eventId' => $event->id,
            'status' => $response->status(),
            'response' => $response->json(),
        ]);

        return null;
    }

    /**
     * Update an existing event in Outlook Calendar.
     */
    public function updateEventInOutlook(CalendarEvent $event, OutlookConnection $connection): bool
    {
        if (! $event->outlookEventId) {
            // Not synced yet — push it instead
            return (bool) $this->pushEventToOutlook($event, $connection);
        }

        $token = $this->getValidAccessToken($connection);

        if (! $token) {
            return false;
        }

        $body = $this->buildOutlookEventBody($event);

        $response = $this->httpWithToken($token)
            ->patch("{$this->graphUrl}/me/events/{$event->outlookEventId}", $body);

        if ($response->successful()) {
            return true;
        }

        // If event not found in Outlook (deleted externally), create a new one
        if ($response->status() === 404) {
            $event->update(['outlookEventId' => null, 'isSyncedToOutlook' => false]);

            return (bool) $this->pushEventToOutlook($event, $connection);
        }

        Log::error('Failed to update event in Outlook', [
            'eventId' => $event->id,
            'outlookEventId' => $event->outlookEventId,
            'status' => $response->status(),
            'response' => $response->json(),
        ]);

        return false;
    }

    /**
     * Delete an event from Outlook Calendar.
     */
    public function deleteEventFromOutlook(string $outlookEventId, OutlookConnection $connection): bool
    {
        $token = $this->getValidAccessToken($connection);

        if (! $token) {
            return false;
        }

        $response = $this->httpWithToken($token)
            ->delete("{$this->graphUrl}/me/events/{$outlookEventId}");

        return $response->successful() || $response->status() === 404;
    }

    /**
     * Build the Microsoft Graph event body from a CalendarEvent.
     */
    protected function buildOutlookEventBody(CalendarEvent $event): array
    {
        $startDateTime = $event->startDateTime;
        $endDateTime = $event->endDateTime ?? $event->startDateTime->copy()->addHour();

        // Microsoft Graph requires all-day events to have start/end at midnight
        if ($event->allDay) {
            $startDateTime = $startDateTime->copy()->startOfDay();
            
            // If the end date is on or before the start date (e.g. single day event), make it exactly 1 day long
            if ($endDateTime->copy()->startOfDay()->lte($startDateTime)) {
                $endDateTime = $startDateTime->copy()->addDay();
            } else {
                // If it's already exactly midnight (e.g. from Outlook sync), keep it. 
                // If it has time (like 23:59:59 from CRM), round up to next midnight.
                if ($endDateTime->format('H:i:s') !== '00:00:00') {
                    $endDateTime = $endDateTime->copy()->startOfDay()->addDay();
                }
            }
        }

        // Convert the internal UTC datetimes so Outlook renders properly in UTC (Outlook automatically translates to user's timezone)
        $body = [
            'subject' => $event->title,
            'body' => [
                'contentType' => 'text',
                'content' => $event->description ?? '',
            ],
            'start' => [
                'dateTime' => $startDateTime->format('Y-m-d\TH:i:s'),
                'timeZone' => 'UTC',
            ],
            'end' => [
                'dateTime' => $endDateTime->format('Y-m-d\TH:i:s'),
                'timeZone' => 'UTC',
            ],
            'isAllDay' => (bool) $event->allDay,
        ];

        if ($event->location) {
            $body['location'] = ['displayName' => $event->location];
        }

        if ($event->reminderSet) {
            $body['isReminderOn'] = true;
            $body['reminderMinutesBeforeStart'] = 15;
        }

        return $body;
    }

    // =========================================================================
    //  PULL: Outlook → CRM (Last Updated Wins)
    // =========================================================================

    /**
     * Fetch events from Outlook and import/sync into CRM.
     */
    public function pullEventsFromOutlook(OutlookConnection $connection, ?string $startDate = null, ?string $endDate = null): array
    {
        $token = $this->getValidAccessToken($connection);

        if (! $token) {
            return ['synced' => 0, 'error' => 'Invalid or expired token'];
        }

        $start = $startDate ? Carbon::parse($startDate) : now()->subMonth();
        $end = $endDate ? Carbon::parse($endDate) : now()->addMonths(3);

        $response = $this->httpWithToken($token)
            ->get("{$this->graphUrl}/me/calendar/events", [
                '$filter' => "start/dateTime ge '{$start->format('Y-m-d\TH:i:s')}' and end/dateTime le '{$end->format('Y-m-d\TH:i:s')}'",
                '$top' => 100,
                '$select' => 'id,subject,bodyPreview,start,end,isAllDay,location,isReminderOn,isCancelled,lastModifiedDateTime',
                '$orderby' => 'start/dateTime',
            ]);

        if (! $response->successful()) {
            $errorBody = $response->json();

            Log::error('Failed to pull events from Outlook', [
                'userId' => $connection->userId,
                'status' => $response->status(),
                'response' => $errorBody,
                'token_prefix' => substr($token, 0, 20).'...',
            ]);

            $graphError = $errorBody['error']['message']
                ?? $errorBody['error']['code']
                ?? ('HTTP '.$response->status());

            // If 401, the user needs to reconnect
            if ($response->status() === 401) {
                $connection->update(['status' => Constants::OUTLOOK_SYNC_STATUS['disconnected']]);

                return ['synced' => 0, 'error' => 'Outlook session expired ('.$graphError.'). Please reconnect via /oauth/microsoft/redirect'];
            }

            // If 403, the token lacks required permissions (Calendar scope missing)
            if ($response->status() === 403) {
                $connection->update(['status' => Constants::OUTLOOK_SYNC_STATUS['disconnected']]);

                return ['synced' => 0, 'error' => 'Insufficient permissions ('.$graphError.'). Please reconnect to grant Calendar access.'];
            }

            return ['synced' => 0, 'error' => 'Outlook API error: '.$graphError];
        }

        $outlookEvents = $response->json('value', []);
        $synced = 0;

        foreach ($outlookEvents as $outlookEvent) {
            if ($outlookEvent['isCancelled'] ?? false) {
                continue;
            }

            // Skip events already synced from CRM → Outlook (avoid duplicates)
            $existing = CalendarEvent::where('outlookEventId', $outlookEvent['id'])->first();

            if ($existing) {
                $outlookModifiedAt = isset($outlookEvent['lastModifiedDateTime']) 
                    ? Carbon::parse($outlookEvent['lastModifiedDateTime'])->setTimezone('UTC') 
                    : Carbon::minValue();
                
                $crmModifiedAt = $existing->updated_at 
                    ? $existing->updated_at->copy()->setTimezone('UTC') 
                    : Carbon::minValue();

                if ($crmModifiedAt->greaterThan($outlookModifiedAt)) {
                    // CRM event is newer. Push to Outlook instead. (Last Updated Wins)
                    $this->updateEventInOutlook($existing, $connection);
                } else {
                    // Outlook event is newer. Update existing event from Outlook.
                    $existing->update([
                        'title' => $outlookEvent['subject'] ?? 'Outlook Event',
                        'description' => $outlookEvent['bodyPreview'] ?? null,
                        'startDateTime' => Carbon::parse($outlookEvent['start']['dateTime']),
                        'endDateTime' => Carbon::parse($outlookEvent['end']['dateTime']),
                        'allDay' => $outlookEvent['isAllDay'] ?? false,
                        'location' => $outlookEvent['location']['displayName'] ?? null,
                    ]);
                }
            } else {
                // Create new event from Outlook
                CalendarEvent::create([
                    'title' => $outlookEvent['subject'] ?? 'Outlook Event',
                    'description' => $outlookEvent['bodyPreview'] ?? null,
                    'eventType' => 'Meeting',
                    'startDateTime' => Carbon::parse($outlookEvent['start']['dateTime']),
                    'endDateTime' => Carbon::parse($outlookEvent['end']['dateTime']),
                    'allDay' => $outlookEvent['isAllDay'] ?? false,
                    'location' => $outlookEvent['location']['displayName'] ?? null,
                    'assignedTo' => $connection->userId,
                    'createdBy' => $connection->userId,
                    'status' => 'scheduled',
                    'color' => '#3B82F6',
                    'outlookEventId' => $outlookEvent['id'],
                    'isFromOutlook' => true,
                    'isSyncedToOutlook' => true,
                    'reminderSet' => $outlookEvent['isReminderOn'] ?? false,
                ]);
            }

            $synced++;
        }

        return ['synced' => $synced, 'total' => count($outlookEvents)];
    }
}
