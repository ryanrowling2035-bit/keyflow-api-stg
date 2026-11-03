<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Leave;
use App\Models\User;
use Carbon\Carbon;
use Tests\TestCase;

class HrOperationsApiTest extends TestCase
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
        $response = $this->getJson('/api/hr/dashboard');
        $response->assertStatus(401);
    }

    public function test_hr_dashboard_retrieval(): void
    {
        $response = $this->getJson('/api/hr/dashboard', $this->authHeaders());
        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'status',
                     'message',
                     'data' => [
                         'active_employees',
                         'employees_on_leave_today',
                         'birthdays',
                         'expiring_documents_count',
                         'pending_leave_requests',
                         'daily_attendance',
                     ],
                 ]);
    }

    public function test_employee_directory_listing(): void
    {
        $response = $this->getJson('/api/hr/employees', $this->authHeaders());
        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'data',
                     'meta' => ['total', 'current_page', 'per_page', 'last_page'],
                 ]);
    }

    public function test_leave_types_listing(): void
    {
        $response = $this->getJson('/api/hr/leaves/types', $this->authHeaders());
        $response->assertStatus(200)
                 ->assertJsonFragment(['Annual Leave'])
                 ->assertJsonFragment(['Sick Leave'])
                 ->assertJsonFragment(['Work From Home']);
    }

    public function test_apply_and_approve_leave_workflow(): void
    {
        // 1. Apply for leave
        $start = Carbon::today()->addDays(5)->toDateString();
        $end = Carbon::today()->addDays(6)->toDateString();

        $applyResponse = $this->postJson('/api/hr/leaves', [
            'leave_type' => 'Emergency Leave',
            'start_date' => $start,
            'end_date'   => $end,
            'total_days' => 2,
            'reason'     => 'Urgent family emergency',
        ], $this->authHeaders());

        $applyResponse->assertStatus(201);
        $leaveId = $applyResponse->json('data.id');
        $this->assertNotNull($leaveId);

        // 2. Approve the leave
        $approveResponse = $this->postJson("/api/hr/leaves/{$leaveId}/approve", [], $this->authHeaders());
        $approveResponse->assertStatus(200);

        $this->assertDatabaseHas('leaves', [
            'id'     => $leaveId,
            'status' => 'approved',
        ]);
    }

    public function test_attendance_marking_and_daily_summary(): void
    {
        $today = Carbon::today()->toDateString();

        // 1. Mark attendance
        $markResponse = $this->postJson('/api/hr/attendance/mark', [
            'user_id'       => $this->user->id,
            'date'          => $today,
            'status'        => 'present',
            'check_in_time' => '09:00',
            'remarks'       => 'On time',
        ], $this->authHeaders());

        $markResponse->assertStatus(200);

        // 2. Daily summary
        $summaryResponse = $this->getJson("/api/hr/attendance/daily-summary?date={$today}", $this->authHeaders());
        $summaryResponse->assertStatus(200)
                        ->assertJsonStructure([
                            'status',
                            'message',
                            'data' => [
                                'date',
                                'total_active',
                                'present',
                                'absent',
                                'half_day',
                                'on_leave',
                                'not_marked',
                            ],
                        ]);
    }

    public function test_birthday_calendar_view(): void
    {
        $response = $this->getJson('/api/hr/dashboard/birthday-calendar', $this->authHeaders());
        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'status',
                     'message',
                     'data',
                 ]);
    }
}

