<?php

namespace App\Services;

use App\Constants\Constants;
use App\Models\EmployeeDocument;
use App\Models\Notification;
use App\Models\User;
use App\Models\UserMeta;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DocumentExpiryNotificationService
{
    protected ?array $cachedHrAdminIds = null;

    public function __construct(
        protected NotificationService $notificationService
    ) {}

    /**
     * Check if a user is an HR Admin (SuperAdmin, Admin, HR, HR Admin).
     */
    public function isHrAdmin(User $user): bool
    {
        $roleName = $user->role?->name ?? '';

        return in_array($roleName, ['SuperAdmin', 'Admin', 'HR', 'HR Admin'], true);
    }

    /**
     * Check if a user is a Manager.
     */
    public function isManager(User $user): bool
    {
        $roleName = $user->role?->name ?? '';

        return strcasecmp($roleName, 'Manager') === 0;
    }

    /**
     * Get all active HR Admin user IDs.
     *
     * @return int[]
     */
    public function getHrAdminIds(): array
    {
        if ($this->cachedHrAdminIds !== null) {
            return $this->cachedHrAdminIds;
        }

        $this->cachedHrAdminIds = User::whereHas('role', function ($q) {
            $q->whereIn('name', ['SuperAdmin', 'Admin', 'HR', 'HR Admin']);
        })
        ->where('status', 'active')
        ->whereNull('deleted_at')
        ->pluck('id')
        ->map(fn ($id) => (int) $id)
        ->unique()
        ->values()
        ->toArray();

        return $this->cachedHrAdminIds;
    }

    /**
     * Resolve all managers belonging to the same team(s) as the given agent.
     * - Agent and Manager belong to the same team.
     * - The user with Manager role in that team is the Manager.
     * - Supports multiple managers in a team or across the agent's teams.
     * - Does NOT return managers of other teams.
     *
     * @return int[]
     */
    public function resolveAgentTeamManagerIds(User $agent): array
    {
        // 1. Get all team IDs the agent belongs to
        $teamIds = DB::table('assign_team_members')
            ->where('userId', $agent->id)
            ->pluck('teamId')
            ->toArray();

        if (empty($teamIds) && method_exists($agent, 'teams')) {
            try {
                $teamIds = $agent->teams()->pluck('teams.id')->toArray();
            } catch (\Throwable $e) {}
        }

        if (empty($teamIds)) {
            // Standalone agent fallback: check user_meta _managerId if not assigned to any team
            try {
                $metaManagerId = UserMeta::where('userId', $agent->id)
                    ->where('metaKey', '_managerId')
                    ->value('metaValue');

                if (! empty($metaManagerId)) {
                    $manager = User::where('id', (int) $metaManagerId)
                        ->where('status', 'active')
                        ->whereNull('deleted_at')
                        ->whereHas('role', fn ($q) => $q->where('name', 'Manager'))
                        ->first();

                    if ($manager) {
                        return [$manager->id];
                    }
                }
            } catch (\Throwable $e) {}

            return [];
        }

        // 2. Query all active users who belong to these teams and have the 'Manager' role
        $managerIds = DB::table('assign_team_members')
            ->join('users', 'assign_team_members.userId', '=', 'users.id')
            ->join('roles', 'users.roleId', '=', 'roles.id')
            ->whereIn('assign_team_members.teamId', $teamIds)
            ->where('roles.name', 'Manager')
            ->where('users.id', '!=', $agent->id)
            ->where('users.status', 'active')
            ->whereNull('users.deleted_at')
            ->pluck('users.id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->toArray();

        return $managerIds;
    }

    /**
     * Resolve notification recipients based on user role:
     *
     * Case 1 (Agent):
     * - HR Admin ko notification.
     * - Agent ke Manager 1 ko notification.
     * - Agent ke Manager 2 ko notification. (Agent ke same Team ke assigned Manager(s))
     * - Agent ko notification.
     *
     * Case 2 (Manager):
     * - HR Admin ko notification.
     * - Manager ko khud notification.
     * (Doosre managers ya agents ko nahi)
     *
     * Case 3 (HR Admin):
     * - Sirf usi HR Admin ko notification.
     * - Kisi doosre HR Admin ya Manager ko nahi.
     *
     * @param  User  $employee  The employee whose document is expiring
     * @return int[] Unique list of recipient user IDs
     */
    public function resolveNotificationRecipients(User $employee): array
    {
        if (! $employee->relationLoaded('role')) {
            $employee->load('role');
        }

        // Case 3: HR Admin document expires
        // - Sirf usi HR Admin ko notification.
        // - Kisi doosre HR Admin ya Manager ko nahi.
        if ($this->isHrAdmin($employee)) {
            return [$employee->id];
        }

        $hrAdminIds = $this->getHrAdminIds();

        // Case 2: Manager document expires
        // - HR Admin ko notification.
        // - Manager ko khud notification.
        if ($this->isManager($employee)) {
            return array_values(array_unique(array_merge(
                [$employee->id],
                $hrAdminIds
            )));
        }

        // Case 1: Agent document expires
        // - HR Admin ko notification.
        // - Agent ke same team ke assigned Manager(s) ko notification.
        // - Agent ko notification.
        $teamManagerIds = $this->resolveAgentTeamManagerIds($employee);

        return array_values(array_unique(array_merge(
            [$employee->id],
            $teamManagerIds,
            $hrAdminIds
        )));
    }

    /**
     * Run daily background check for document expiries across all active users.
     *
     * @param  int[]  $intervals  Days before expiry to check [30, 15, 7, 0]
     * @return int Total notifications generated
     */
    public function checkAndDispatchDailyNotifications(array $intervals = [30, 15, 7, 0]): int
    {
        $today = Carbon::today();
        $notificationsSent = 0;
        $dispatchedToday = [];

        foreach ($intervals as $days) {
            $checkDate = $today->copy()->addDays($days)->toDateString();

            // 1. Check user_meta for document expiries
            $metaKeyMap = [
                '_visaExpiryDate'           => 'Visa',
                '_passportExpiryDate'       => 'Passport',
                '_emiratesIdExpiryDate'     => 'Emirates ID',
                '_insuranceExpiryDate'      => 'Insurance',
                '_labourContractExpiryDate' => 'Labour Card',
                '_labourCardExpiryDate'     => 'Labour Card',
            ];

            $userMetas = UserMeta::with(['user.role'])
                ->whereIn('metaKey', array_keys($metaKeyMap))
                ->whereDate('metaValue', $checkDate)
                ->get();

            foreach ($userMetas as $meta) {
                if ($meta->user && $meta->user->status === 'active') {
                    $docName = $metaKeyMap[$meta->metaKey] ?? 'Document';
                    $dedupeKey = "{$meta->user->id}_{$docName}_{$days}";

                    if (! isset($dispatchedToday[$dedupeKey])) {
                        $sent = $this->dispatchExpiryNotification($meta->user, $docName, $days);
                        $dispatchedToday[$dedupeKey] = true;
                        $notificationsSent += $sent;
                    }
                }
            }

            // 2. Check employee_documents table
            $documents = EmployeeDocument::with(['user.role'])
                ->whereDate('expiry_date', $checkDate)
                ->get();

            foreach ($documents as $doc) {
                if ($doc->user && $doc->user->status === 'active') {
                    $normalizedType = match ($doc->document_type) {
                        'Labour Contract', 'Labour Card' => 'Labour Card',
                        'Medical Insurance', 'Insurance' => 'Insurance',
                        default => $doc->document_type,
                    };

                    $dedupeKey = "{$doc->user->id}_{$normalizedType}_{$days}";

                    if (! isset($dispatchedToday[$dedupeKey])) {
                        $docLabel = ($doc->title && $doc->title !== $doc->document_type)
                            ? "{$doc->document_type} ({$doc->title})"
                            : $doc->document_type;

                        $sent = $this->dispatchExpiryNotification($doc->user, $docLabel, $days);
                        $dispatchedToday[$dedupeKey] = true;
                        $notificationsSent += $sent;
                    }
                }
            }
        }

        Log::info("HR Document Expiry Check: {$notificationsSent} notifications dispatched.");

        return $notificationsSent;
    }

    /**
     * Dispatch notification to resolved recipients for an employee's expiring document.
     *
     * @return int Number of notifications created
     */
    public function dispatchExpiryNotification(User $employee, string $docName, int $days): int
    {
        $timeText = ($days === 0) ? 'expires today' : "expires in {$days} days";
        $message = "{$docName} of {$employee->name} {$timeText}. Please renew and upload updated copy.";
        $title = ($days === 0) ? "URGENT: {$docName} Expired" : "Upcoming Expiry: {$docName}";

        $recipients = $this->resolveNotificationRecipients($employee);
        $count = 0;

        foreach ($recipients as $recipientId) {
            // Avoid duplicate notification for the exact same alert today
            $alreadySent = Notification::where('userId', $recipientId)
                ->where('type', 'document_expiry')
                ->where('actorId', $employee->id)
                ->whereDate('created_at', Carbon::today())
                ->where('message', $message)
                ->exists();

            if (! $alreadySent) {
                $this->notificationService->create([
                    'userId'   => $recipientId,
                    'type'     => Constants::NOTIFICATION_TYPES['document_expiry'] ?? 'document_expiry',
                    'title'    => $title,
                    'message'  => $message,
                    'module'   => Constants::MODULE_DOCUMENT ?? 'employee_document',
                    'moduleId' => $employee->id,
                    'actorId'  => $employee->id,
                    'data'     => [
                        'employeeId'    => $employee->id,
                        'employeeName'  => $employee->name,
                        'documentType'  => $docName,
                        'daysRemaining' => $days,
                    ],
                    'isRead'   => false,
                ]);

                $count++;
            }
        }

        return $count;
    }
}

