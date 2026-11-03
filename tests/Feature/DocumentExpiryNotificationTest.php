<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Models\UserMeta;
use App\Services\DocumentExpiryNotificationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DocumentExpiryNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected DocumentExpiryNotificationService $service;
    protected Role $adminRole;
    protected Role $hrRole;
    protected Role $managerRole;
    protected Role $agentRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(DocumentExpiryNotificationService::class);

        // Create standard roles
        $this->adminRole = Role::firstOrCreate(['name' => 'Admin'], ['status' => 'Active']);
        $this->hrRole = Role::firstOrCreate(['name' => 'HR'], ['status' => 'Active']);
        $this->managerRole = Role::firstOrCreate(['name' => 'Manager'], ['status' => 'Active']);
        $this->agentRole = Role::firstOrCreate(['name' => 'Agent'], ['status' => 'Active']);
    }

    public function test_case_1_agent_document_expiry_notifies_agent_team_managers_and_hr_admin(): void
    {
        // 1. HR Admin users
        $hrAdmin1 = User::factory()->create(['roleId' => $this->adminRole->id, 'status' => 'active']);
        $hrAdmin2 = User::factory()->create(['roleId' => $this->hrRole->id, 'status' => 'active']);

        // 2. Team A with Agent and 2 Managers
        $teamA = Team::create(['name' => 'Sales Team A', 'status' => 'active']);

        $managerA1 = User::factory()->create(['roleId' => $this->managerRole->id, 'status' => 'active']);
        $managerA2 = User::factory()->create(['roleId' => $this->managerRole->id, 'status' => 'active']);
        $agentA = User::factory()->create(['roleId' => $this->agentRole->id, 'status' => 'active']);

        DB::table('assign_team_members')->insert([
            ['teamId' => $teamA->id, 'userId' => $managerA1->id, 'created_at' => now(), 'updated_at' => now()],
            ['teamId' => $teamA->id, 'userId' => $managerA2->id, 'created_at' => now(), 'updated_at' => now()],
            ['teamId' => $teamA->id, 'userId' => $agentA->id, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // 3. Team B with a Manager who should NOT receive notification for Agent A
        $teamB = Team::create(['name' => 'Leasing Team B', 'status' => 'active']);
        $managerB = User::factory()->create(['roleId' => $this->managerRole->id, 'status' => 'active']);
        DB::table('assign_team_members')->insert([
            ['teamId' => $teamB->id, 'userId' => $managerB->id, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Resolve recipients for agentA
        $recipients = $this->service->resolveNotificationRecipients($agentA);

        // Verification:
        // - Agent himself is notified
        $this->assertContains($agentA->id, $recipients);

        // - Both managers from Agent's Team A are notified
        $this->assertContains($managerA1->id, $recipients);
        $this->assertContains($managerA2->id, $recipients);

        // - HR Admins are notified
        $this->assertContains($hrAdmin1->id, $recipients);
        $this->assertContains($hrAdmin2->id, $recipients);

        // - Manager from Team B is NOT notified
        $this->assertNotContains($managerB->id, $recipients);
    }

    public function test_case_2_manager_document_expiry_notifies_only_manager_and_hr_admins(): void
    {
        // 1. HR Admins
        $hrAdmin = User::factory()->create(['roleId' => $this->adminRole->id, 'status' => 'active']);

        // 2. Team with 2 Managers and an Agent
        $team = Team::create(['name' => 'Alpha Team', 'status' => 'active']);
        $manager1 = User::factory()->create(['roleId' => $this->managerRole->id, 'status' => 'active']);
        $manager2 = User::factory()->create(['roleId' => $this->managerRole->id, 'status' => 'active']);
        $agent = User::factory()->create(['roleId' => $this->agentRole->id, 'status' => 'active']);

        DB::table('assign_team_members')->insert([
            ['teamId' => $team->id, 'userId' => $manager1->id, 'created_at' => now(), 'updated_at' => now()],
            ['teamId' => $team->id, 'userId' => $manager2->id, 'created_at' => now(), 'updated_at' => now()],
            ['teamId' => $team->id, 'userId' => $agent->id, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Resolve recipients when Manager 1's document expires
        $recipients = $this->service->resolveNotificationRecipients($manager1);

        // Verification:
        // - Manager 1 is notified
        $this->assertContains($manager1->id, $recipients);

        // - HR Admin is notified
        $this->assertContains($hrAdmin->id, $recipients);

        // - Peer Manager 2 is NOT notified
        $this->assertNotContains($manager2->id, $recipients);

        // - Agent is NOT notified
        $this->assertNotContains($agent->id, $recipients);
    }

    public function test_case_3_hr_admin_document_expiry_notifies_only_that_specific_hr_admin(): void
    {
        // 1. Two HR Admins
        $hrAdmin1 = User::factory()->create(['roleId' => $this->adminRole->id, 'status' => 'active']);
        $hrAdmin2 = User::factory()->create(['roleId' => $this->hrRole->id, 'status' => 'active']);

        // 2. Manager and Agent
        $manager = User::factory()->create(['roleId' => $this->managerRole->id, 'status' => 'active']);
        $agent = User::factory()->create(['roleId' => $this->agentRole->id, 'status' => 'active']);

        // Resolve recipients when hrAdmin1's document expires
        $recipients = $this->service->resolveNotificationRecipients($hrAdmin1);

        // Verification:
        // - Only hrAdmin1 gets notified
        $this->assertEquals([$hrAdmin1->id], $recipients);

        // - No other HR Admin gets notified
        $this->assertNotContains($hrAdmin2->id, $recipients);

        // - No Manager gets notified
        $this->assertNotContains($manager->id, $recipients);

        // - No Agent gets notified
        $this->assertNotContains($agent->id, $recipients);
    }

    public function test_duplicate_notifications_are_prevented_on_same_day(): void
    {
        $hrAdmin = User::factory()->create(['roleId' => $this->adminRole->id, 'status' => 'active']);
        $agent = User::factory()->create(['roleId' => $this->agentRole->id, 'status' => 'active']);

        // Set Visa expiry to 30 days from now
        $expiryDate = Carbon::today()->addDays(30)->toDateString();
        UserMeta::create([
            'userId'    => $agent->id,
            'metaKey'   => '_visaExpiryDate',
            'metaValue' => $expiryDate,
        ]);

        // First dispatch run
        $firstRunCount = $this->service->checkAndDispatchDailyNotifications([30]);
        $this->assertGreaterThan(0, $firstRunCount);

        $initialNotificationCount = Notification::count();

        // Second dispatch run on same day (simulating repeated cron execution)
        $secondRunCount = $this->service->checkAndDispatchDailyNotifications([30]);

        // Second run must NOT create duplicate notifications
        $this->assertEquals(0, $secondRunCount);
        $this->assertEquals($initialNotificationCount, Notification::count());
    }
}

