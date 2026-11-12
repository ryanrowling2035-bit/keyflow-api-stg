<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\ContactEmail;
use App\Models\ContactNote;
use App\Models\OutlookConnection;
use App\Models\UnmatchedEmail;
use App\Models\User;
use App\Repositories\ContactNoteRepository;
use App\Services\OutlookEmailService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class OutlookSyncTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;
    protected OutlookConnection $connection;
    protected OutlookEmailService $emailService;

    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'mysql',
            'database.connections.mysql.database' => 'apndxmain',
        ]);

        ContactEmail::where('message_id', 'like', 'msg_%')->delete();
        UnmatchedEmail::withTrashed()->where('messageId', 'like', 'msg_%')->forceDelete();
        Contact::where('email', 'like', '%@example.com')->orWhere('email', 'like', '%@externalmail.com')->delete();

        $this->emailService = new OutlookEmailService();

        // Find or create test user
        $this->user = User::first() ?? User::factory()->create([
            'email' => 'agent@exclusive-links.com'
        ]);

        $this->token = auth('api')->tokenById($this->user->id);

        // Mock connection
        $this->connection = OutlookConnection::updateOrCreate(
            ['userId' => $this->user->id],
            [
                'accessToken' => 'mock_token',
                'refreshToken' => 'mock_refresh',
                'tokenExpiresAt' => now()->addHour(),
                'outlookEmail' => 'agent@exclusive-links.com',
                'status' => 'connected',
            ]
        );
    }

    public function test_internal_email_filtering()
    {
        // 1. Company domain should be detected as internal
        $this->assertTrue($this->emailService->isInternalEmail('colleague@exclusive-links.com', $this->connection));
        $this->assertTrue($this->emailService->isInternalEmail('manager@keyflow.com', $this->connection));

        // 2. Client emails should NOT be internal
        $this->assertFalse($this->emailService->isInternalEmail('buyer.dubai@gmail.com', $this->connection));
        $this->assertFalse($this->emailService->isInternalEmail('investor@yahoo.co.uk', $this->connection));

        // 3. User in CRM database should be internal
        $this->assertTrue($this->emailService->isInternalEmail($this->user->email, $this->connection));
    }

    public function test_incoming_email_from_matching_contact_is_linked()
    {
        $contact = Contact::create([
            'name' => 'John Client',
            'email' => 'john.client@example.com',
            'phone' => '+971501234567',
        ]);

        $mockEmails = [
            [
                'id' => 'msg_unique_101',
                'conversationId' => 'conv_thread_101',
                'sender' => [
                    'emailAddress' => [
                        'name' => 'John Client',
                        'address' => 'john.client@example.com'
                    ]
                ],
                'toRecipients' => [
                    [
                        'emailAddress' => [
                            'name' => 'Agent',
                            'address' => 'agent@exclusive-links.com'
                        ]
                    ]
                ],
                'subject' => 'Inquiry about Downtown Apartment',
                'bodyPreview' => 'Hi, is this unit still available?',
                'body' => ['content' => '<p>Hi, is this unit still available?</p>'],
                'receivedDateTime' => now()->toIso8601String(),
            ]
        ];

        $this->emailService->processAndLinkEmails($mockEmails, $this->connection);

        // Assert ContactEmail was created with correct data and threading
        $savedEmail = ContactEmail::where('message_id', 'msg_unique_101')->first();
        $this->assertNotNull($savedEmail, 'ContactEmail should be created for matched contact');
        $this->assertEquals($contact->id, $savedEmail->contact_id);
        $this->assertEquals($this->user->id, $savedEmail->user_id);
        $this->assertEquals('conv_thread_101', $savedEmail->conversation_id);
        $this->assertTrue($savedEmail->is_incoming);
        $this->assertEquals('Inquiry about Downtown Apartment', $savedEmail->subject);

        // Assert it is NOT in unmatched queue
        $this->assertFalse(UnmatchedEmail::where('messageId', 'msg_unique_101')->exists());
    }

    public function test_outgoing_email_sent_from_outlook_is_linked_to_contact()
    {
        $contact = Contact::create([
            'name' => 'Jane Buyer',
            'email' => 'jane.buyer@example.com',
            'phone' => '+971509876543',
        ]);

        $mockEmails = [
            [
                'id' => 'msg_outgoing_102',
                'folder' => 'SentItems',
                'is_outgoing' => true,
                'conversationId' => 'conv_thread_102',
                'sender' => [
                    'emailAddress' => [
                        'name' => 'Agent Alias',
                        'address' => 'agent.alias@externalmail.com'
                    ]
                ],
                'from' => [
                    'emailAddress' => [
                        'name' => 'Agent Alias',
                        'address' => 'agent.alias@externalmail.com'
                    ]
                ],
                'toRecipients' => [
                    [
                        'emailAddress' => [
                            'name' => 'Jane Buyer',
                            'address' => 'jane.buyer@example.com'
                        ]
                    ]
                ],
                'subject' => 'Listing Brochure for Downtown Penthouse',
                'bodyPreview' => 'Please find attached the requested brochure.',
                'body' => ['content' => '<p>Please find attached the requested brochure.</p>'],
                'sentDateTime' => now()->toIso8601String(),
                'receivedDateTime' => now()->toIso8601String(),
            ]
        ];

        $this->emailService->processAndLinkEmails($mockEmails, $this->connection);

        // Assert ContactEmail was created with is_incoming = false and linked to Jane Buyer
        $savedEmail = ContactEmail::where('message_id', 'msg_outgoing_102')->first();
        $this->assertNotNull($savedEmail, 'ContactEmail should be created for matched contact on outgoing email');
        $this->assertEquals($contact->id, $savedEmail->contact_id);
        $this->assertEquals($this->user->id, $savedEmail->user_id);
        $this->assertEquals('conv_thread_102', $savedEmail->conversation_id);
        $this->assertFalse((bool)$savedEmail->is_incoming, 'Must be marked as outgoing (is_incoming = false)');
        $this->assertEquals('Listing Brochure for Downtown Penthouse', $savedEmail->subject);

        // Assert it appears in Contact timeline
        $repo = new ContactNoteRepository();
        $timelineEmails = $repo->getByContactId($contact->id, 'email');
        $found = collect($timelineEmails)->firstWhere('id', 'ce_' . $savedEmail->id);
        $this->assertNotNull($found, 'Must appear in contact email communication');
        $this->assertEquals('outgoing', $found['content']['direction']);
        $this->assertEquals('jane.buyer@example.com', $found['content']['to']);
    }

    public function test_incoming_email_without_contact_goes_to_unmatched_queue()
    {
        $unknownSender = 'unknown.person.' . uniqid() . '@externalmail.com';

        $mockEmails = [
            [
                'id' => 'msg_unmatched_202',
                'conversationId' => 'conv_thread_202',
                'sender' => [
                    'emailAddress' => [
                        'name' => 'Unknown Person',
                        'address' => $unknownSender
                    ]
                ],
                'toRecipients' => [
                    [
                        'emailAddress' => [
                            'name' => 'Agent',
                            'address' => 'agent@exclusive-links.com'
                        ]
                    ]
                ],
                'subject' => 'New Villa Listing Inquiry',
                'bodyPreview' => 'Hello, I want to view the villa',
                'body' => ['content' => '<p>Hello, I want to view the villa</p>'],
                'receivedDateTime' => now()->toIso8601String(),
            ]
        ];

        $this->emailService->processAndLinkEmails($mockEmails, $this->connection);

        // Assert UnmatchedEmail was created
        $unmatched = UnmatchedEmail::where('messageId', 'msg_unmatched_202')->first();
        $this->assertNotNull($unmatched, 'Unmatched email should be queued');
        $this->assertEquals($this->user->id, $unmatched->user_id);
        $this->assertEquals($unknownSender, $unmatched->senderEmail);
        $this->assertEquals('New Villa Listing Inquiry', $unmatched->subject);
    }

    public function test_internal_company_emails_are_excluded()
    {
        $mockEmails = [
            [
                'id' => 'msg_internal_303',
                'conversationId' => 'conv_internal_303',
                'sender' => [
                    'emailAddress' => [
                        'name' => 'HR Team',
                        'address' => 'hr@exclusive-links.com'
                    ]
                ],
                'toRecipients' => [
                    [
                        'emailAddress' => [
                            'name' => 'Agent',
                            'address' => 'agent@exclusive-links.com'
                        ]
                    ]
                ],
                'subject' => 'Internal Policy Update',
                'bodyPreview' => 'Please review the new policy',
                'body' => ['content' => '<p>Please review the new policy</p>'],
                'receivedDateTime' => now()->toIso8601String(),
            ]
        ];

        $this->emailService->processAndLinkEmails($mockEmails, $this->connection);

        // Should be neither in ContactEmail nor in UnmatchedEmail
        $this->assertFalse(ContactEmail::where('message_id', 'msg_internal_303')->exists(), 'Internal email should not be in ContactEmail');
        $this->assertFalse(UnmatchedEmail::where('messageId', 'msg_internal_303')->exists(), 'Internal email should not be in UnmatchedEmail');
    }

    public function test_duplicate_prevention_prevents_re_inserting()
    {
        $contact = Contact::create([
            'name' => 'Duplicate Test Contact',
            'email' => 'dupe.contact@example.com',
            'phone' => '+971509998877',
        ]);

        $mockEmail = [
            'id' => 'msg_dupe_404',
            'conversationId' => 'conv_dupe_404',
            'sender' => [
                'emailAddress' => [
                    'name' => 'Duplicate Test Contact',
                    'address' => 'dupe.contact@example.com'
                ]
            ],
            'toRecipients' => [
                [
                    'emailAddress' => [
                        'name' => 'Agent',
                        'address' => 'agent@exclusive-links.com'
                    ]
                ]
            ],
            'subject' => 'Duplicate Prevention Check',
            'bodyPreview' => 'Testing duplicate prevention',
            'body' => ['content' => '<p>Testing duplicate prevention</p>'],
            'receivedDateTime' => now()->toIso8601String(),
        ];

        // Process once
        $this->emailService->processAndLinkEmails([$mockEmail], $this->connection);
        $countAfterFirst = ContactEmail::where('message_id', 'msg_dupe_404')->count();
        $this->assertEquals(1, $countAfterFirst);

        // Process again with same ID
        $this->emailService->processAndLinkEmails([$mockEmail], $this->connection);
        $countAfterSecond = ContactEmail::where('message_id', 'msg_dupe_404')->count();
        $this->assertEquals(1, $countAfterSecond, 'Duplicate email should not create a second row');
    }

    public function test_link_unmatched_email_to_contact_via_api()
    {
        $contact = Contact::create([
            'name' => 'Target Contact',
            'email' => 'target@example.com',
            'phone' => '+971501112233',
        ]);

        $unmatched = UnmatchedEmail::create([
            'user_id' => $this->user->id,
            'messageId' => 'msg_link_505',
            'senderName' => 'Lead Sender',
            'senderEmail' => 'lead.sender@external.com',
            'subject' => 'Ready to purchase',
            'snippet' => 'Ready to buy immediately',
            'body' => '<p>Ready to buy immediately</p>',
            'dateReceived' => now(),
        ]);

        $response = $this->withHeaders([
                'Authorization' => 'Bearer ' . $this->token,
                'Accept' => 'application/json'
            ])->postJson("/api/unmatched-emails/{$unmatched->id}/link-contact", [
                'contactId' => $contact->id
            ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        // Unmatched email should now be deleted from queue
        $this->assertNull(UnmatchedEmail::find($unmatched->id));

        // And present in ContactEmail
        $linked = ContactEmail::where('message_id', 'msg_link_505')->first();
        $this->assertNotNull($linked);
        $this->assertEquals($contact->id, $linked->contact_id);
    }

    public function test_ignore_archive_unmatched_email_via_api()
    {
        $unmatched = UnmatchedEmail::create([
            'user_id' => $this->user->id,
            'messageId' => 'msg_ignore_606',
            'senderName' => 'Spam Sender',
            'senderEmail' => 'spam@promotions.com',
            'subject' => 'Win a million dollars',
            'snippet' => 'Spam content',
            'dateReceived' => now(),
        ]);

        $response = $this->withHeaders([
                'Authorization' => 'Bearer ' . $this->token,
                'Accept' => 'application/json'
            ])->deleteJson("/api/unmatched-emails/{$unmatched->id}");

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        // Should be soft deleted
        $this->assertNull(UnmatchedEmail::find($unmatched->id));
        $this->assertNotNull(UnmatchedEmail::withTrashed()->find($unmatched->id));
    }

    public function test_contact_history_merges_synced_emails()
    {
        $contact = Contact::create([
            'name' => 'Timeline Contact',
            'email' => 'timeline@example.com',
            'phone' => '+971502223344',
        ]);

        // Create a native CRM note
        ContactNote::create([
            'contactId' => $contact->id,
            'createdBy' => $this->user->id,
            'type' => 'email',
            'content' => 'CRM sent email content',
            'parent' => 0,
        ]);

        // Create a synced Outlook email
        ContactEmail::create([
            'contact_id' => $contact->id,
            'user_id' => $this->user->id,
            'message_id' => 'msg_timeline_707',
            'conversation_id' => 'conv_timeline_707',
            'sender_name' => 'Timeline Contact',
            'sender_email' => 'timeline@example.com',
            'subject' => 'Outlook reply',
            'body' => '<p>Outlook synced message</p>',
            'is_incoming' => true,
            'received_at' => now(),
        ]);

        $repo = new ContactNoteRepository();
        $emails = $repo->getByContactId($contact->id, 'email');

        // Should contain 2 items: 1 CRM note + 1 Outlook synced email
        $this->assertCount(2, $emails);

        $hasOutlook = collect($emails)->contains(function ($item) {
            return isset($item['content']['isFromOutlook']) && $item['content']['isFromOutlook'] === true;
        });
        $this->assertTrue($hasOutlook, 'Timeline must include Outlook synced email');
    }

    public function test_outlook_event_is_stored_and_returned_in_calendar_events_api()
    {
        $event = \App\Models\CalendarEvent::create([
            'title' => 'Outlook Test Appointment',
            'description' => 'Test sync from outlook',
            'eventType' => 'Meeting',
            'startDateTime' => now()->addDay(),
            'endDateTime' => now()->addDay()->addHour(),
            'allDay' => false,
            'assignedTo' => $this->user->id,
            'createdBy' => $this->user->id,
            'status' => 'scheduled',
            'color' => '#3B82F6',
            'outlookEventId' => 'AQMkADAwA_TEST_EVENT_ID',
            'isFromOutlook' => true,
            'isSyncedToOutlook' => true,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/calendar/events');

        $response->assertStatus(200);
        $data = $response->json('data');
        
        $found = collect($data)->firstWhere('id', $event->id);
        $this->assertNotNull($found, 'Outlook synced event must appear in /api/calendar/events');
        $this->assertTrue($found['isFromOutlook'], 'isFromOutlook flag must be true');
        $this->assertEquals('AQMkADAwA_TEST_EVENT_ID', $found['outlookEventId']);
    }
}
