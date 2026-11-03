<?php

namespace Tests\Feature;

use App\Models\GreetingTemplate;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GreetingTemplateApiTest extends TestCase
{
    protected string $token;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::first() ?? User::factory()->create();
        $this->token = auth('api')->tokenById($this->user->id);
    }

    protected function authHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->token,
            'Accept'        => 'application/json',
        ];
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $response = $this->getJson('/api/settings/greeting-templates');
        $response->assertStatus(401);
    }

    public function test_create_birthday_and_holiday_templates(): void
    {
        // 1. Create Birthday Template
        $birthdayPayload = [
            'name'            => 'VIP Client Birthday',
            'type'            => 'Birthday',
            'trigger_event'   => 'On Birthday',
            'send_time'       => '09:00',
            'status'          => 'Active',
            'target_audience' => 'Clients',
            'subject'         => 'Happy Birthday {Contact_First_Name}!',
            'body'            => '<p>Wishing you a wonderful birthday from {Company_Name}!</p>',
        ];

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/settings/greeting-templates', $birthdayPayload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'VIP Client Birthday',
                    'type' => 'Birthday',
                ],
            ]);

        $this->assertDatabaseHas('greeting_templates', [
            'name' => 'VIP Client Birthday',
            'type' => 'Birthday',
        ]);

        // 2. Create Holiday Template
        $holidayPayload = [
            'name'            => 'New Year Greetings',
            'type'            => 'Holiday',
            'holiday_date'    => '2027-01-01',
            'send_time'       => '10:00',
            'status'          => 'Active',
            'target_audience' => 'Clients',
            'subject'         => 'Happy New Year {Contact_First_Name}!',
            'body'            => '<p>Happy New Year from {Agent_Name}!</p>',
        ];

        $responseHoliday = $this->withHeaders($this->authHeaders())
            ->postJson('/api/settings/greeting-templates', $holidayPayload);

        $responseHoliday->assertStatus(201);
        $this->assertDatabaseHas('greeting_templates', [
            'name' => 'New Year Greetings',
            'type' => 'Holiday',
        ]);
    }

    public function test_list_templates_with_filters(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/settings/greeting-templates?type=Birthday&status=Active');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
                'meta' => ['total', 'current_page', 'per_page', 'last_page'],
            ]);
    }

    public function test_show_and_update_template(): void
    {
        $template = GreetingTemplate::first();

        // Show
        $showResponse = $this->withHeaders($this->authHeaders())
            ->getJson("/api/settings/greeting-templates/{$template->id}");

        $showResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $template->id,
                ],
            ]);

        // Update
        $updatePayload = [
            'name'    => 'Updated Template Name',
            'status'  => 'Disabled',
            'subject' => 'Updated Subject',
        ];

        $updateResponse = $this->withHeaders($this->authHeaders())
            ->putJson("/api/settings/greeting-templates/{$template->id}", $updatePayload);

        $updateResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'name'   => 'Updated Template Name',
                    'status' => 'Disabled',
                ],
            ]);
    }

    public function test_send_test_email(): void
    {
        Mail::fake();

        $template = GreetingTemplate::first();

        $response = $this->withHeaders($this->authHeaders())
            ->postJson("/api/settings/greeting-templates/{$template->id}/test", [
                'email' => 'testrecipient@example.com',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        Mail::assertSent(\App\Mail\GreetingTestMail::class);
    }

    public function test_trigger_cron(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/settings/greeting-templates/cron');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Automated greeting check executed successfully.',
            ]);
    }

    public function test_upload_image_and_show(): void
    {
        $file = UploadedFile::fake()->image('greeting_banner.jpg', 600, 400);

        $uploadResponse = $this->withHeaders($this->authHeaders())
            ->postJson('/api/settings/greeting-templates/upload-image', [
                'image' => $file,
            ]);

        $uploadResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $imageUrl = $uploadResponse->json('data.url');
        $this->assertNotEmpty($imageUrl);

        // Fetch the uploaded image through the public image route
        $parts = explode('/api/greetings/image/', $imageUrl);
        $filename = end($parts);

        $imageResponse = $this->get("/api/greetings/image/{$filename}");
        $imageResponse->assertStatus(200);

        // Cleanup
        $filePath = storage_path('app/greetings/' . $filename);
        if (file_exists($filePath)) {
            @unlink($filePath);
        }
    }

    public function test_logs_endpoint(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/settings/greeting-templates/logs');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
                'meta' => ['total', 'current_page', 'per_page', 'last_page'],
            ]);
    }

    public function test_delete_template(): void
    {
        $template = GreetingTemplate::first();

        $response = $this->withHeaders($this->authHeaders())
            ->deleteJson("/api/settings/greeting-templates/{$template->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Greeting template deleted successfully',
            ]);

        $this->assertSoftDeleted('greeting_templates', [
            'id' => $template->id,
        ]);
    }
}
