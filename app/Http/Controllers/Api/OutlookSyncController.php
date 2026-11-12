<?php

namespace App\Http\Controllers\Api;

use App\Constants\Constants;
use App\Http\Controllers\Controller;
use App\Models\CalendarEvent;
use App\Models\OutlookConnection;
use App\Services\OutlookCalendarService;
use App\Services\OutlookEmailService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use League\OAuth2\Client\Provider\GenericProvider;

class OutlookSyncController extends Controller
{
    use ApiResponse;

    public function __construct() {}

    /**
     * Get Outlook connection status for the authenticated user.
     */
    public function status()
    {
        try {
            $connection = OutlookConnection::where('userId', user()->id)->first();

            if (! $connection) {
                return $this->success([
                    'status' => 'not_connected',
                    'outlookEmail' => null,
                ], 'Outlook connection status fetched');
            }

            return $this->success([
                'status' => $connection->status,
                'outlookEmail' => $connection->outlookEmail,
                'tokenExpiresAt' => $connection->tokenExpiresAt,
            ], 'Outlook connection status fetched');
        } catch (\Exception $e) {
            return $this->exceptionResponse('OutlookSyncController : status', $e);
        }
    }

    /**
     * Disconnect the Outlook account.
     */
    public function disconnect()
    {
        try {
            $connection = OutlookConnection::where('userId', user()->id)->first();

            if (! $connection) {
                return $this->error('No Outlook connection found', 422);
            }

            $connection->delete();

            return $this->success([], 'Outlook account disconnected successfully');
        } catch (\Exception $e) {
            return $this->exceptionResponse('OutlookSyncController : disconnect', $e);
        }
    }

    /**
     * Sync Outlook calendar events into CRM.
     */
    public function syncCalendar(Request $request, OutlookCalendarService $calendarService)
    {
        try {
            $connection = OutlookConnection::where('userId', user()->id)->first();

            if (! $connection) {
                return $this->error('No Outlook connection found', 422);
            }

            $startDate = $request->input('startDate');
            $endDate = $request->input('endDate');

            $result = $calendarService->pullEventsFromOutlook($connection, $startDate, $endDate);

            if (isset($result['error'])) {
                return $this->error($result['error'], 400);
            }

            // Update last sync time
            $connection->update(['lastCalendarSyncAt' => now()]);

            return $this->success($result, 'Calendar synced successfully');
        } catch (\Exception $e) {
            return $this->exceptionResponse('OutlookSyncController : syncCalendar', $e);
        }
    }

    /**
     * Sync Outlook emails into CRM.
     */
    public function syncEmail(Request $request, OutlookEmailService $emailService)
    {
        try {
            $connection = OutlookConnection::where('userId', user()->id)->first();

            if (! $connection) {
                return $this->error('No Outlook connection found', 422);
            }

            $result = $emailService->pullEmailsFromOutlook($connection);

            if (isset($result['error'])) {
                return $this->error($result['error'], 400);
            }

            // Save emails to the UnmatchedEmail table or link them to Contacts
            if (isset($result['emails']) && is_array($result['emails'])) {
                $emailService->processAndLinkEmails($result['emails'], $connection);
            }

            // Update last sync time
            $connection->update(['lastEmailSyncAt' => now()]);

            return $this->success($result, 'Emails synced successfully');
        } catch (\Exception $e) {
            return $this->exceptionResponse('OutlookSyncController : syncEmail', $e);
        }
    }

    private function getMicrosoftProvider(): GenericProvider
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

    public function redirectToMicrosoft()
    {
        try {
            $provider = $this->getMicrosoftProvider();

            $authorizationUrl = $provider->getAuthorizationUrl([
                'scope' => 'offline_access Mail.Read User.Read Calendars.ReadWrite',
            ]);

            $state = $provider->getState();
            Cache::put('ms_oauth2state_'.$state, user()->id, now()->addDay());

            $connection = OutlookConnection::where('userId', user()->id)->first();

            return $this->success([
                'redirect_url' => $authorizationUrl,
                'current_status' => $connection ? $connection->status : 'not_connected',
                'outlookEmail' => $connection?->outlookEmail,
            ], 'Microsoft OAuth URL generated successfully');
        } catch (\Exception $e) {
            return $this->exceptionResponse('OutlookSyncController : redirectToMicrosoft', $e);
        }
    }

    public function handleMicrosoftCallback(Request $request)
    {
        $frontendUrl = env('FRONTEND_URL');

        if ($request->has('error')) {
            return redirect()->to($frontendUrl.'/dashboard?oauth=error&message='.urlencode($request->error_description ?? $request->error));
        }

        if (! $request->has('code') || ! $request->has('state')) {
            return redirect()->to($frontendUrl.'/dashboard?oauth=error&message='.urlencode('Authorization code or state not found.'));
        }

        $userId = Cache::pull('ms_oauth2state_'.$request->state);

        if (! $userId) {
            return redirect()->to($frontendUrl.'/dashboard?oauth=error&message='.urlencode('Invalid or expired state parameter.'));
        }

        $provider = $this->getMicrosoftProvider();

        try {
            $accessToken = $provider->getAccessToken('authorization_code', [
                'code' => $request->code,
            ]);

            $refreshToken = $accessToken->getRefreshToken();
            $expiresAt = now()->addSeconds($accessToken->getExpires() - time());

            $httpRequest = app()->isLocal() ? Http::withoutVerifying() : Http::withToken($accessToken->getToken());
            $response = $httpRequest->withToken($accessToken->getToken())
                ->get('https://graph.microsoft.com/v1.0/me');

            $profile = $response->json();
            $email = $profile['mail']
                ?? $profile['otherMails'][0]
                ?? $profile['userPrincipalName']
                ?? null;

            $existingConnection = OutlookConnection::where('userId', $userId)->first();

            if ($existingConnection) {
                CalendarEvent::where('isFromOutlook', true)
                    ->where(function ($q) use ($userId) {
                        $q->where('assignedTo', $userId)
                            ->orWhere('createdBy', $userId);
                    })
                    ->delete();
            }

            OutlookConnection::updateOrCreate(
                ['userId' => $userId],
                [
                    'accessToken' => $accessToken->getToken(),
                    'refreshToken' => $refreshToken,
                    'tokenExpiresAt' => $expiresAt,
                    'outlookEmail' => $email,
                    'status' => Constants::OUTLOOK_SYNC_STATUS['connected'],
                    'lastSyncAt' => null,
                    'lastEmailSyncAt' => null,
                    'lastCalendarSyncAt' => null,
                    'lastTaskSyncAt' => null,
                    'syncSettings' => null,
                ]
            );

            return redirect()->to($frontendUrl.'/dashboard/agents/'.$userId.'/view?oauth=success&email='.urlencode($email ?? ''));
        } catch (\Exception $e) {
            Log::error('OutlookSyncController : handleMicrosoftCallback : '.$e->getMessage());

            return redirect()->to($frontendUrl.'/dashboard/agents/'.$userId.'/view?oauth=error&message='.urlencode('Something went wrong. Please contact support for assistance.'));
        }
    }
}
