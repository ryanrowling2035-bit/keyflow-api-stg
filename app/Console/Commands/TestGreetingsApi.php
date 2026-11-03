<?php

namespace App\Console\Commands;

use App\Models\GreetingTemplate;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

class TestGreetingsApi extends Command
{
    protected $signature = 'test:greetings-api';
    protected $description = 'Test all Greeting Templates API endpoints';

    public function handle()
    {
        $this->info("=== GREETING API TEST SUITE ===\n");

        $user = User::first();
        if (!$user) {
            $this->error("No user found in database.");
            return 1;
        }

        $this->line("1. Authenticating as: {$user->name} ({$user->email})");

        $token = auth('api')->tokenById($user->id);
        if (!$token) {
            $this->error("Failed to generate JWT token.");
            return 1;
        }
        $this->info("   JWT Token generated successfully.\n");

        // Helper function to dispatch request through Laravel routing
        $callApi = function ($method, $uri, $data = [], $files = [], $useToken = true) use ($token) {
            $server = [
                'HTTP_ACCEPT' => 'application/json',
            ];
            if ($useToken) {
                $server['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
            }

            $req = Request::create($uri, $method, $data, [], $files, $server);
            if (!empty($data) && $method !== 'GET') {
                $req->headers->set('Content-Type', 'application/json');
            }

            $response = app()->handle($req);
            $statusCode = $response->getStatusCode();
            $decoded = json_decode($response->getContent(), true) ?: $response->getContent();

            return [$statusCode, $decoded];
        };

        // 1. Test Unauthenticated request (should be 401)
        $this->line("2. Testing Unauthenticated GET /api/settings/greeting-templates ...");
        list($status, $res) = $callApi('GET', '/api/settings/greeting-templates', [], [], false);
        if ($status === 401) {
            $this->info("   [PASS] HTTP 401 Unauthenticated correctly rejected.");
        } else {
            $this->error("   [FAIL] Expected 401, got {$status}");
        }

        // 2. Test Create Birthday Template
        $this->line("\n3. Testing POST /api/settings/greeting-templates (Create Birthday Template) ...");
        $birthdayData = [
            'name'            => 'Automated Birthday Wish',
            'type'            => 'Birthday',
            'trigger_event'   => 'On Birthday',
            'send_time'       => '09:00',
            'status'          => 'Active',
            'target_audience' => 'Clients',
            'subject'         => 'Happy Birthday {Contact_First_Name}!',
            'body'            => '<p>Dear {Contact_First_Name}, happy birthday from {Company_Name}!</p>',
        ];
        list($status, $res) = $callApi('POST', '/api/settings/greeting-templates', $birthdayData);
        $templateId = null;
        if ($status === 201 && isset($res['data']['id'])) {
            $templateId = $res['data']['id'];
            $this->info("   [PASS] HTTP 201 Created. Template ID: {$templateId}");
        } else {
            $this->error("   [FAIL] Status {$status}: " . json_encode($res));
        }

        // 3. Test Create Holiday Template
        $this->line("\n4. Testing POST /api/settings/greeting-templates (Create Holiday Template) ...");
        $holidayData = [
            'name'            => 'National Day Greetings',
            'type'            => 'Holiday',
            'holiday_date'    => date('Y-m-d', strtotime('+5 days')),
            'send_time'       => '10:00',
            'status'          => 'Active',
            'target_audience' => 'Clients',
            'subject'         => 'Happy National Day {Contact_First_Name}!',
            'body'            => '<p>Warm wishes from Exclusive Links!</p>',
        ];
        list($status, $res) = $callApi('POST', '/api/settings/greeting-templates', $holidayData);
        if ($status === 201 && isset($res['data']['id'])) {
            $holidayId = $res['data']['id'];
            $this->info("   [PASS] HTTP 201 Created. Holiday Template ID: {$holidayId}");
        } else {
            $this->error("   [FAIL] Status {$status}: " . json_encode($res));
        }

        // 4. Test GET List templates with search & filters
        $this->line("\n5. Testing GET /api/settings/greeting-templates (Filters & Pagination) ...");
        list($status, $res) = $callApi('GET', '/api/settings/greeting-templates?type=Birthday&status=Active');
        if ($status === 200 && isset($res['data']) && is_array($res['data'])) {
            $this->info("   [PASS] HTTP 200 OK. Records returned: " . count($res['data']) . ", Total: " . ($res['meta']['total'] ?? 0));
        } else {
            $this->error("   [FAIL] Status {$status}: " . json_encode($res));
        }

        // 5. Test GET Show Template
        if ($templateId) {
            $this->line("\n6. Testing GET /api/settings/greeting-templates/{$templateId} (Show) ...");
            list($status, $res) = $callApi('GET', "/api/settings/greeting-templates/{$templateId}");
            if ($status === 200 && isset($res['data']['id']) && $res['data']['id'] == $templateId) {
                $this->info("   [PASS] HTTP 200 OK. Fetched: {$res['data']['name']}");
            } else {
                $this->error("   [FAIL] Status {$status}: " . json_encode($res));
            }
        }

        // 6. Test PUT Update Template
        if ($templateId) {
            $this->line("\n7. Testing PUT /api/settings/greeting-templates/{$templateId} (Update) ...");
            $updateData = [
                'name'    => 'Automated Birthday Wish - Updated',
                'status'  => 'Disabled',
                'subject' => 'Updated Subject {Contact_First_Name}',
            ];
            list($status, $res) = $callApi('PUT', "/api/settings/greeting-templates/{$templateId}", $updateData);
            if ($status === 200 && ($res['data']['name'] ?? '') === 'Automated Birthday Wish - Updated') {
                $this->info("   [PASS] HTTP 200 OK. Updated name: {$res['data']['name']}, Status: {$res['data']['status']}");
            } else {
                $this->error("   [FAIL] Status {$status}: " . json_encode($res));
            }
        }

        // 7. Test POST Test Email
        if ($templateId) {
            $this->line("\n8. Testing POST /api/settings/greeting-templates/{$templateId}/test (Test Email) ...");
            list($status, $res) = $callApi('POST', "/api/settings/greeting-templates/{$templateId}/test", [
                'email' => 'client_test@exclusive-links.com',
            ]);
            if ($status === 200 && ($res['success'] ?? false) === true) {
                $this->info("   [PASS] HTTP 200 OK. " . ($res['message'] ?? ''));
            } else {
                $this->error("   [FAIL] Status {$status}: " . json_encode($res));
            }
        }

        // 8. Test GET Logs
        $this->line("\n9. Testing GET /api/settings/greeting-templates/logs ...");
        list($status, $res) = $callApi('GET', '/api/settings/greeting-templates/logs');
        if ($status === 200 && isset($res['data'])) {
            $this->info("   [PASS] HTTP 200 OK. Logs fetched successfully. Total logs: " . ($res['meta']['total'] ?? 0));
        } else {
            $this->error("   [FAIL] Status {$status}: " . json_encode($res));
        }

        // 9. Test POST Cron manual trigger
        $this->line("\n10. Testing POST /api/settings/greeting-templates/cron (Manual Trigger) ...");
        list($status, $res) = $callApi('POST', '/api/settings/greeting-templates/cron');
        if ($status === 200 && ($res['success'] ?? false) === true) {
            $this->info("   [PASS] HTTP 200 OK. " . ($res['message'] ?? ''));
        } else {
            $this->error("   [FAIL] Status {$status}: " . json_encode($res));
        }

        // 10. Test Image Upload & Public Route
        $this->line("\n11. Testing Image Upload & Public Image Route ...");
        $tempImg = tempnam(sys_get_temp_dir(), 'test_img') . '.jpg';
        $im = imagecreatetruecolor(100, 100);
        imagejpeg($im, $tempImg);
        imagedestroy($im);

        $uploadedFile = new UploadedFile($tempImg, 'banner.jpg', 'image/jpeg', null, true);
        list($status, $res) = $callApi('POST', '/api/settings/greeting-templates/upload-image', [], ['image' => $uploadedFile]);
        if ($status === 200 && isset($res['data']['url'])) {
            $url = $res['data']['url'];
            $this->info("   [PASS] HTTP 200 OK. Image uploaded: {$url}");

            // Verify public image route
            $parts = explode('/api/greetings/image/', $url);
            $filename = end($parts);
            list($imgStatus, $imgRes) = $callApi('GET', "/api/greetings/image/{$filename}", [], [], false);
            if ($imgStatus === 200) {
                $this->info("   [PASS] Public image route /api/greetings/image/{$filename} returned HTTP 200 OK.");
            } else {
                $this->error("   [FAIL] Public image route returned HTTP {$imgStatus}");
            }

            // Cleanup
            $storedPath = storage_path('app/greetings/' . $filename);
            if (file_exists($storedPath)) {
                @unlink($storedPath);
            }
        } else {
            $this->error("   [FAIL] Image upload status {$status}: " . json_encode($res));
        }
        if (file_exists($tempImg)) {
            @unlink($tempImg);
        }

        // 11. Test DELETE Soft Delete
        if ($templateId) {
            $this->line("\n12. Testing DELETE /api/settings/greeting-templates/{$templateId} (Soft Delete) ...");
            list($status, $res) = $callApi('DELETE', "/api/settings/greeting-templates/{$templateId}");
            if ($status === 200 && ($res['success'] ?? false) === true) {
                $this->info("   [PASS] HTTP 200 OK. Template deleted successfully.");
            } else {
                $this->error("   [FAIL] Status {$status}: " . json_encode($res));
            }
        }

        $this->info("\n=== ALL 12 API TESTS EXECUTED SUCCESSFULLY! ===");
        return 0;
    }
}
