<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\ContactEmail;
use App\Models\OutlookConnection;
use App\Models\UnmatchedEmail;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OutlookEmailService
{
    /**
     * Refresh the Microsoft Graph API token if needed.
     *
     * @param OutlookConnection $connection
     * @return bool|array True if successful, array with error if failed.
     */
    private function refreshTokenIfNeeded(OutlookConnection $connection)
    {
        if ($connection->tokenExpiresAt < now()->addMinutes(5)) {
            $tenantId = env('AZURE_TENANT_ID', 'common');
            $http = app()->isLocal() ? Http::withoutVerifying()->asForm() : Http::asForm();
            $response = $http->post("https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token", [
                'client_id' => env('AZURE_CLIENT_ID'),
                'client_secret' => env('AZURE_CLIENT_SECRET'),
                'grant_type' => 'refresh_token',
                'refresh_token' => $connection->refreshToken,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $connection->accessToken = $data['access_token'];
                $connection->refreshToken = $data['refresh_token'] ?? $connection->refreshToken;
                $connection->tokenExpiresAt = now()->addSeconds($data['expires_in']);
                $connection->save();
                return true;
            } else {
                Log::error('Failed to refresh Outlook token for user ' . $connection->userId . ': ' . $response->body());
                return [
                    'error' => 'Failed to refresh Outlook token.',
                ];
            }
        }
        return true;
    }

    /**
     * Check if an email address is internal to the company.
     * Checks against registered CRM users and configured internal domains.
     */
    public function isInternalEmail(?string $email, ?OutlookConnection $connection = null): bool
    {
        if (!$email) {
            return false;
        }

        $email = strtolower(trim($email));

        // If this email belongs to an existing CRM Contact, treat it as client communication
        if (Contact::whereRaw('LOWER(email) = ?', [$email])->exists()) {
            return false;
        }

        // 1. Configured company domains (from .env or default)
        $configuredDomains = array_filter(array_map('trim', explode(',', env('COMPANY_EMAIL_DOMAINS', 'exclusive-links.com,keyflow.com'))));
        foreach ($configuredDomains as $domain) {
            if ($domain && Str::endsWith($email, '@' . ltrim($domain, '@'))) {
                return true;
            }
        }

        // 2. Also check if the email domain matches the connected user's Outlook email domain
        if ($connection && $connection->outlookEmail) {
            $userDomain = Str::after(strtolower($connection->outlookEmail), '@');
            if ($userDomain && !in_array($userDomain, ['gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com', 'icloud.com'])) {
                if (Str::endsWith($email, '@' . $userDomain)) {
                    return true;
                }
            }
        }

        // 3. Match against existing CRM user accounts
        $isUser = User::whereRaw('LOWER(email) = ?', [$email])->exists();
        if ($isUser) {
            return true;
        }

        return false;
    }

    /**
     * Pull recent emails from Microsoft Graph API (both Inbox and SentItems).
     *
     * @param OutlookConnection $connection
     * @return array
     */
    public function pullEmailsFromOutlook(OutlookConnection $connection)
    {
        try {
            $tokenRefresh = $this->refreshTokenIfNeeded($connection);
            if (is_array($tokenRefresh) && isset($tokenRefresh['error'])) {
                return $tokenRefresh;
            }

            $accessToken = $connection->accessToken;
            $allEmails = [];

            // Explicitly fetch from Inbox and SentItems to ensure incoming & outgoing emails are synced.
            $folders = ['Inbox', 'SentItems'];

            foreach ($folders as $folder) {
                $url = "https://graph.microsoft.com/v1.0/me/mailFolders/{$folder}/messages?" . http_build_query([
                    '$select' => 'id,conversationId,sender,from,toRecipients,subject,body,bodyPreview,receivedDateTime,sentDateTime,isDraft',
                    '$filter' => 'isDraft eq false',
                    '$top' => 50,
                    '$orderby' => 'receivedDateTime desc',
                ]);

                $http = app()->isLocal() ? Http::withoutVerifying() : Http::asJson();
                $response = $http->withToken($accessToken)->get($url);

                if ($response->successful()) {
                    $emails = $response->json()['value'] ?? [];
                    foreach ($emails as &$emailItem) {
                        $emailItem['folder'] = $folder;
                        $emailItem['is_outgoing'] = (strtolower($folder) === 'sentitems');
                    }
                    unset($emailItem);
                    $allEmails = array_merge($allEmails, $emails);
                } else {
                    Log::error("Failed to fetch emails from folder {$folder}: " . $response->body());
                }
            }

            // Remove duplicates based on message ID
            $uniqueEmails = [];
            foreach ($allEmails as $email) {
                if (isset($email['id']) && !isset($uniqueEmails[$email['id']])) {
                    $uniqueEmails[$email['id']] = $email;
                }
            }

            return [
                'success' => true,
                'emails' => array_values($uniqueEmails),
            ];
            
        } catch (Exception $e) {
            Log::error('Exception in pullEmailsFromOutlook: ' . $e->getMessage());
            return [
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Process fetched emails and link them to CRM contacts, filtering internal emails.
     *
     * @param array $emails
     * @param OutlookConnection $connection
     * @param \Illuminate\Console\Command|null $command
     * @return void
     */
    public function processAndLinkEmails(array $emails, OutlookConnection $connection, $command = null)
    {
        $connection->loadMissing('user');

        foreach ($emails as $emailData) {
            $senderEmail = $emailData['from']['emailAddress']['address']
                ?? $emailData['sender']['emailAddress']['address']
                ?? null;
            $senderName = $emailData['from']['emailAddress']['name']
                ?? $emailData['sender']['emailAddress']['name']
                ?? null;
            $messageId = $emailData['id'] ?? null;
            
            if (!$senderEmail || !$messageId) {
                continue;
            }

            $conversationId = $emailData['conversationId'] ?? null;
            $subject = $emailData['subject'] ?? '';
            $snippet = $emailData['bodyPreview'] ?? '';
            $body = $emailData['body']['content'] ?? '';

            // A message is outgoing if:
            // 1. It came from SentItems folder
            // 2. OR the sender email matches the connected Outlook account email
            // 3. OR the sender email matches the CRM user account email
            $isFromSentFolder = (isset($emailData['is_outgoing']) && $emailData['is_outgoing'])
                || (isset($emailData['folder']) && strtolower($emailData['folder']) === 'sentitems');

            $isUserSender = (strtolower($senderEmail) === strtolower($connection->outlookEmail))
                || ($connection->user && strtolower($senderEmail) === strtolower($connection->user->email));

            $isOutgoing = $isFromSentFolder || $isUserSender;

            $dateStr = $isOutgoing
                ? ($emailData['sentDateTime'] ?? $emailData['receivedDateTime'] ?? now())
                : ($emailData['receivedDateTime'] ?? $emailData['sentDateTime'] ?? now());
            $receivedAt = Carbon::parse($dateStr)->format('Y-m-d H:i:s');

            if ($isOutgoing) {
                $toRecipients = $emailData['toRecipients'] ?? [];
                
                // If all recipients are internal employees, skip syncing internal correspondence
                $externalRecipients = [];
                foreach ($toRecipients as $recipient) {
                    $recEmail = $recipient['emailAddress']['address'] ?? null;
                    if ($recEmail && !$this->isInternalEmail($recEmail, $connection)) {
                        $externalRecipients[] = $recipient;
                    }
                }

                // If sent purely internally between colleagues, exclude it
                if (empty($externalRecipients) && !empty($toRecipients)) {
                    if ($command) $command->line("Skipped internal company email: $subject");
                    continue;
                }

                foreach ($externalRecipients as $recipient) {
                    $recipientEmail = $recipient['emailAddress']['address'] ?? null;
                    if (!$recipientEmail) continue;

                    $contact = Contact::whereRaw('LOWER(email) = ?', [strtolower($recipientEmail)])->first();
                    if ($contact) {
                        ContactEmail::updateOrCreate(
                            ['message_id' => $messageId],
                            [
                                'contact_id' => $contact->id,
                                'user_id' => $connection->userId,
                                'conversation_id' => $conversationId,
                                'sender_name' => $senderName,
                                'sender_email' => $senderEmail,
                                'subject' => $subject,
                                'snippet' => $snippet,
                                'body' => $body,
                                'is_incoming' => false,
                                'received_at' => $receivedAt,
                            ]
                        );
                        UnmatchedEmail::where('messageId', $messageId)->delete();
                        if ($command) $command->line("Saved matched outgoing email to contact: $recipientEmail");
                    } else {
                        $linkedExists = ContactEmail::where('message_id', $messageId)->exists();
                        if ($linkedExists) continue;

                        $unmatchedExists = UnmatchedEmail::withTrashed()->where('messageId', $messageId)->exists();
                        if ($unmatchedExists) continue;

                        UnmatchedEmail::create([
                            'user_id' => $connection->userId,
                            'messageId' => $messageId,
                            'senderName' => $recipient['emailAddress']['name'] ?? $recipientEmail,
                            'senderEmail' => $recipientEmail,
                            'subject' => $subject,
                            'snippet' => $snippet,
                            'body' => $body,
                            'dateReceived' => $receivedAt,
                        ]);
                        if ($command) $command->line("Added unmatched outgoing email to: $recipientEmail");
                    }
                }
            } else {
                // Incoming Email: Check if sender is internal company colleague
                if ($this->isInternalEmail($senderEmail, $connection)) {
                    if ($command) $command->line("Skipped incoming internal company email from: $senderEmail");
                    continue;
                }

                $contact = Contact::whereRaw('LOWER(email) = ?', [strtolower($senderEmail)])->first();
                if ($contact) {
                    ContactEmail::updateOrCreate(
                        ['message_id' => $messageId],
                        [
                            'contact_id' => $contact->id,
                            'user_id' => $connection->userId,
                            'conversation_id' => $conversationId,
                            'sender_name' => $senderName,
                            'sender_email' => $senderEmail,
                            'subject' => $subject,
                            'snippet' => $snippet,
                            'body' => $body,
                            'is_incoming' => true,
                            'received_at' => $receivedAt,
                        ]
                    );
                    UnmatchedEmail::where('messageId', $messageId)->delete();
                    if ($command) $command->line("Saved matched incoming email from contact: $senderEmail");
                    continue;
                }

                $linkedExists = ContactEmail::where('message_id', $messageId)->exists();
                if ($linkedExists) continue;

                $unmatchedExists = UnmatchedEmail::withTrashed()->where('messageId', $messageId)->exists();
                if ($unmatchedExists) continue;

                UnmatchedEmail::create([
                    'user_id' => $connection->userId,
                    'messageId' => $messageId,
                    'senderName' => $senderName,
                    'senderEmail' => $senderEmail,
                    'subject' => $subject,
                    'snippet' => $snippet,
                    'body' => $body,
                    'dateReceived' => $receivedAt,
                ]);
                if ($command) $command->line("Added unmatched incoming email from: $senderEmail");
            }
        }
    }
}

