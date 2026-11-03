<?php

namespace App\Services;

use App\Constants\Constants;
use App\Http\Resources\EnquiryResource\GetAllEnquiryResource;
use App\Http\Resources\TaskResources\TaskResource;
use App\Models\AssignTeamMember;
use App\Models\Closing;
use App\Models\Contact;
use App\Models\ContactNote;
use App\Models\Enquiry;
use App\Models\LoginHistory;
use App\Models\Offer;
use App\Models\Property;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    public function __construct(protected TeamService $teamService) {}

    public function adminDashboard(Request $request): array
    {
        $startDate = $request->query('startDate');
        $endDate = $request->query('endDate');

        $applyDateFilter = function ($query) use ($startDate, $endDate) {
            if ($startDate) {
                $query->where('created_at', '>=', localDayStartUtc($startDate));
            }
            if ($endDate) {
                $query->where('created_at', '<=', localDayEndUtc($endDate));
            }
        };

        // globalTeam filter: a superadmin can scope every metric to one team's members.
        // null = no team selected = whole company (so $applyScope is a no-op and the
        // default view is unchanged). Same resolver the manager dashboard uses.
        $scopeIds = $this->managerScopeUserIds($request);
        $applyScope = function ($query, string $column) use ($scopeIds) {
            if ($scopeIds !== null) {
                $query->whereIn($column, $scopeIds);
            }
        };

        $data = [
            'totalPendingListings' => Property::query()
                ->where('status', Constants::PROPERTY_STATUS_PENDING)
                ->tap(fn ($q) => $applyScope($q, 'agentId'))
                ->tap($applyDateFilter)->count(),

            'totalPermitRequestedListings' => Property::query()
                ->where('approvalStatus', Constants::PERMIT_REQUESTED)
                ->tap(fn ($q) => $applyScope($q, 'agentId'))
                ->tap($applyDateFilter)->count(),

            'totalWaitingForApprovalListings' => Property::query()
                ->where('approvalStatus', Constants::APPROVAL_REQUESTED)
                ->tap(fn ($q) => $applyScope($q, 'agentId'))
                ->tap($applyDateFilter)->count(),

            'totalEnquiries' => Enquiry::query()
                ->tap(fn ($q) => $applyScope($q, 'agentId'))
                ->tap($applyDateFilter)->count(),
            // Aliases are excluded so merging two records for one person makes the
            // count go down by one, rather than still counting them both.
            'totalContacts' => Contact::query()->notMerged()
                ->when($scopeIds !== null, fn ($q) => $q->where(function ($sub) use ($scopeIds) {
                    $sub->whereIn('createdBy', $scopeIds)
                        ->orWhereHas('ownedListings', fn ($l) => $l->whereIn('agentId', $scopeIds))
                        ->orWhereHas('enquiries', fn ($e) => $e->whereIn('agentId', $scopeIds));
                }))
                ->tap($applyDateFilter)->count(),
            'totalOffers' => Offer::query()
                ->tap(fn ($q) => $applyScope($q, 'assignedAgentId'))
                ->tap($applyDateFilter)->count(),
            'totalClosings' => Closing::query()
                ->whereIn('status', [Constants::CLOSE_STATUS_OPEN, Constants::CLOSE_STATUS_CLOSED])
                ->tap(fn ($q) => $applyScope($q, 'assignedAgentId'))
                ->when($startDate, fn ($q) => $q->where('created_at', '>=', localDayStartUtc($startDate)))
                ->when($endDate, fn ($q) => $q->where('created_at', '<=', localDayEndUtc($endDate)))
                ->count(),
            'totalListings' => Property::query()
                ->tap(fn ($q) => $applyScope($q, 'agentId'))
                ->tap($applyDateFilter)->count(),
            'totalTasks' => Task::query()
                ->tap(fn ($q) => $applyScope($q, 'assignedTo'))
                ->tap($applyDateFilter)->count(),

            'totalActiveAgents' => User::where('status', 'active')
                ->when($scopeIds !== null, fn ($q) => $q->whereIn('id', $scopeIds))
                ->whereHas('role', fn ($q) => $q->where('name', 'Agent'))
                ->count(),

            'totalLeads' => $this->totalLeadsByScore(function ($q) use ($scopeIds, $applyDateFilter) {
                if ($scopeIds !== null) {
                    $q->whereIn('agentId', $scopeIds);
                }
                // Open Game leads are an unassigned pool, not anyone's "total
                // leads" — exclude them so the widget reconciles with the pie
                // drill-down (which lands on the isOpenGame=0 enquiries list).
                $q->where('isOpenGame', 0);
                $applyDateFilter($q);
            }),
        ];

        // dueDate is a DATE column, so compare it directly instead of via whereDate().
        // Wrapping the column in DATE() stops MySQL using the (dueDate, status) index
        // and was full-scanning the whole tasks table (~440k rows) on every tile.
        $today = Carbon::today()->toDateString();

        $openTaskQuery = fn () => Task::query()
            ->whereNotIn('status', Constants::TASK_CLOSED_STATUSES)
            ->when($scopeIds !== null, fn ($q) => $q->whereIn('assignedTo', $scopeIds))
            ->with(['assignedUser', 'creator']);

        $data['todaysTasks'] = TaskResource::collection(
            $openTaskQuery()->where('dueDate', $today)->orderBy('dueDate')->limit(50)->get()
        );

        $data['hotTasks'] = TaskResource::collection(
            $openTaskQuery()->where('priority', 'high')->where('dueDate', '>=', $today)->orderBy('dueDate')->limit(50)->get()
        );

        $data['overdueTasks'] = TaskResource::collection(
            $openTaskQuery()->where('dueDate', '<', $today)->orderBy('dueDate', 'desc')->limit(50)->get()
        );

        $data['upcomingTasks'] = TaskResource::collection(
            $openTaskQuery()->where('dueDate', '>', $today)->orderBy('dueDate')->limit(50)->get()
        );

        $fiveDaysAhead = Carbon::today()->addDays(5)->toDateString();

        $todaysFollowUpsQuery = $openTaskQuery()->where('dueDate', $today)->orderBy('dueDate');
        $overdueFollowUpsQuery = $openTaskQuery()->where('dueDate', '<', $today)->orderBy('dueDate', 'desc');
        $upcomingFollowUpsQuery = $openTaskQuery()
            ->where('dueDate', '>', $today)
            ->where('dueDate', '<=', $fiveDaysAhead)
            ->orderBy('dueDate');

        $data['todaysFollowUps'] = [
            'count' => $todaysFollowUpsQuery->count(),
            'data' => TaskResource::collection($todaysFollowUpsQuery->limit(50)->get()),
        ];

        $data['overdueFollowUps'] = [
            'count' => $overdueFollowUpsQuery->count(),
            'data' => TaskResource::collection($overdueFollowUpsQuery->limit(50)->get()),
        ];

        $data['upcomingFollowUps'] = [
            'count' => $upcomingFollowUpsQuery->count(),
            'data' => TaskResource::collection($upcomingFollowUpsQuery->limit(50)->get()),
        ];

        $teamId = $request->query('teamId');
        $data['teams'] = $this->teamService->selectTeams();
        $data['teamOverview'] = $this->teamOverview($teamId ? (int) $teamId : null, $startDate, $endDate);
        $data['totalAdmins'] = $scopeIds === null ? $this->admins()
            : User::where('status', 'active')->whereIn('id', $scopeIds)
                ->whereHas('role', fn ($q) => $q->whereNotIn('name', [Constants::AGENT, Constants::MANAGER, Constants::HR]))->count();
        $data['totalManagers'] = $scopeIds === null ? $this->managers()
            : User::where('status', 'active')->whereIn('id', $scopeIds)
                ->whereHas('role', fn ($q) => $q->where('name', Constants::MANAGER))->count();
        $data['totalAgents'] = $scopeIds === null ? $this->agents()
            : User::where('status', 'active')->whereIn('id', $scopeIds)
                ->whereHas('role', fn ($q) => $q->where('name', Constants::AGENT))->count();
        $data['usersLoggedInLast7Days'] = $this->usersLoggedInLast7Days($startDate, $endDate, $scopeIds);
        $data['leaderboard'] = $this->leaderboard($request);
        $data['overview'] = $this->overview($request, $scopeIds);
        $data['priorityActions'] = $this->priorityActions($scopeIds);
        $data['popups'] = $this->dashboardPopups($scopeIds);

        return $data;
    }

    /**
     * Celebration/attention popups for the Manager/Admin/Super Admin dashboard:
     *  - newLeadsToday: count of enquiries created today (Dubai calendar day). 0
     *    when none — the frontend hides the popup.
     *  - newDealClosed: the most recently won closing (status open/closed, i.e.
     *    not cancelled) to celebrate, or null when there are none. `id` is the
     *    CLOSING id — the "View Deal" link opens /closings/{id}/view.
     *
     * Both honour the same team scope as the rest of the dashboard (globalTeam).
     *
     * @param  array<int, int>|null  $scopeIds  null = whole company
     * @param  int|null  $currentUserId
     * @return array{newLeadsToday: int, newDealClosed: array<string, mixed>|null, birthdayGreeting: array<string, mixed>|null}
     */
    protected function dashboardPopups(?array $scopeIds, ?int $currentUserId = null): array
    {
        $currentUserId = $currentUserId ?? ($scopeIds && count($scopeIds) === 1 ? $scopeIds[0] : (auth()->id() ?? user()?->id));

        $newLeadsToday = Enquiry::query()
            ->when($scopeIds !== null, fn ($q) => $q->whereIn('agentId', $scopeIds))
            ->whereBetween('created_at', [localDayStartUtc(), localDayEndUtc()])
            ->count();

        $closing = Closing::query()
            ->whereIn('status', [Constants::CLOSE_STATUS_OPEN, Constants::CLOSE_STATUS_CLOSED])
            ->when($scopeIds !== null, fn ($q) => $q->whereIn('assignedAgentId', $scopeIds))
            ->with(['property:id,title', 'assignedAgent:id,name', 'buyer:id,name', 'buyerTenantContact:id,name'])
            ->latest('created_at')
            ->latest('id')
            ->first();

        $birthdayGreeting = null;
        if ($currentUserId) {
            $user = User::find($currentUserId);
            if ($user && $user->date_of_birth) {
                try {
                    $dob = Carbon::parse($user->date_of_birth);
                    $today = Carbon::today();
                    if ($dob->month === $today->month && $dob->day === $today->day) {
                        $birthdayGreeting = [
                            'isBirthday' => true,
                            'name' => $user->name,
                            'message' => "Wishing you a very Happy Birthday, {$user->name}!",
                            'date' => $today->toDateString(),
                        ];
                    }
                } catch (\Exception $e) {
                    // Ignore date parsing failure
                }
            }
        }

        return [
            'newLeadsToday' => $newLeadsToday,
            'newDealClosed' => $closing ? $this->formatDealPopup($closing) : null,
            'birthdayGreeting' => $birthdayGreeting,
        ];
    }

    /**
     * Shape a closing into the "Deal Closed" popup payload. `value` is a
     * pre-formatted display string; `type` is the Sale/Rent display label.
     *
     * @return array<string, mixed>
     */
    protected function formatDealPopup(Closing $closing): array
    {
        $isRent = in_array($closing->listingType, ['rent', 'vacationHomes'], true);

        return [
            'id' => $closing->id,
            'property' => $closing->property->title ?? $closing->name,
            'agent' => $closing->assignedAgent->name ?? null,
            'client' => $closing->buyer?->masterOrSelf()->name ?? ($closing->buyerTenantContact?->masterOrSelf()->name ?? null),
            'type' => $isRent ? 'Rent' : 'Sale',
            'value' => 'AED '.number_format((float) $closing->saleRentalPrice),
            'commission' => 'AED '.number_format((float) $closing->totalCommission),
        ];
    }

    /**
     * Resolve the team member user ids a manager is allowed to see.
     *
     * Defaults to the members of every team the manager belongs to; when a valid
     * `globalTeam` is supplied (and it is one of the manager's teams) the scope is
     * narrowed to that single team.
     *
     * @return array<int, int>
     */
    protected function managerScopeUserIds(Request $request): ?array
    {
        $globalTeam = $request->query('globalTeam');
        // uncomment the commeted code if data is again needed on the basis of manager
        // if (isManager()) {
        //     // Manager: limited to their own teams, optionally narrowed to one of them.
        //     $managerTeamIds = user()->teams()->pluck('teams.id')->map(fn ($id) => (int) $id)->all();
        //     if ($globalTeam !== null && $globalTeam !== '' && in_array((int) $globalTeam, $managerTeamIds, true)) {
        //         $teamIds = [(int) $globalTeam];
        //     } else {
        //         $teamIds = $managerTeamIds;
        //     }

        //     if (empty($teamIds)) {
        //         return [];
        //     }
        // } else {

        if ($globalTeam === null || $globalTeam === '') {
            return null;
        }
        $teamIds = [(int) $globalTeam];
        // }

        return AssignTeamMember::whereIn('teamId', $teamIds)
            ->pluck('userId')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    public function agentDashboard(Request $request): array
    {
        $userId = $request->input('agentId') ?? user()->id;
        $startDate = $request->query('startDate');
        $endDate = $request->query('endDate');
        $hasDateRange = $startDate !== null || $endDate !== null;

        // created_at is a UTC datetime, so the selected local day must be
        // converted to UTC boundaries. dueDate is a plain DATE column and is
        // compared against the calendar date as-is (no timezone shift).
        $applyDateFilter = function ($query, string $column = 'created_at') use ($startDate, $endDate) {
            $isDateColumn = $column === 'dueDate';
            if ($startDate) {
                $query->where($column, '>=', $isDateColumn ? $startDate.' 00:00:00' : localDayStartUtc($startDate));
            }
            if ($endDate) {
                $query->where($column, '<=', $isDateColumn ? $endDate.' 23:59:59' : localDayEndUtc($endDate));
            }
        };

        return [
            'myLiveListings' => Property::where('agentId', $userId)
                ->whereIn('status', Constants::MATCHING_ALLOWED_STATUSES)
                ->tap(fn ($q) => $applyDateFilter($q))
                ->count(),

            'myActiveInquiries' => Enquiry::where('agentId', $userId)
                ->where('isOpenGame', 0)
                ->whereIn('status', Constants::ENQUIRY_ACTIVE_STATUSES)
                ->tap(fn ($q) => $applyDateFilter($q))
                ->count(),

            'totalDealsClosed' => Closing::where('assignedAgentId', $userId)
                ->whereIn('status', [Constants::CLOSE_STATUS_OPEN, Constants::CLOSE_STATUS_CLOSED])
                ->tap(fn ($q) => $applyDateFilter($q, 'created_at'))
                ->count(),

            'totalCommission' => (float) Closing::where('assignedAgentId', $userId)
                ->whereIn('status', [Constants::CLOSE_STATUS_OPEN, Constants::CLOSE_STATUS_CLOSED])
                ->tap(fn ($q) => $applyDateFilter($q, 'created_at'))
                ->sum('totalCommission'),

            'activeOffers' => Offer::where('assignedAgentId', $userId)
                ->where('status', Constants::OFFER_STATUS_NEW)
                ->tap(fn ($q) => $applyDateFilter($q))
                ->count(),

            'newInquiriesToday' => Enquiry::where('agentId', $userId)
                ->where('isOpenGame', 0)
                ->whereBetween('created_at', [localDayStartUtc(), localDayEndUtc()])
                ->count(),

            'totalContacts' => Contact::accessibleByAgent($userId)
                ->tap(fn ($q) => $applyDateFilter($q))
                ->count(),

            'totalLeads' => $this->totalLeadsByScore(function ($q) use ($userId, $applyDateFilter) {
                $q->where('agentId', $userId)->where('isOpenGame', 0);
                $applyDateFilter($q);
            }),

            'todaysTasks' => $this->agentTodaysTasks($userId),
            'overdueTasks' => $this->agentOverdueTasks($userId),
            // The Agent View "Overdue Follow-ups" tab reads this. Same overdue set
            // as overdueTasks but paginated (page / perPage) so the tab can page
            // past the first 50. Scoped to $userId, which honours the agentId param.
            'overdueFollowUps' => $this->agentOverdueFollowUps($userId, $request),
            'myHotEnquiries' => $this->agentHotEnquiries($userId, $applyDateFilter),
            'myNewEnquiries' => $this->agentNewEnquiries($userId, $applyDateFilter),
            'openGameEnquiries' => $this->openGameEnquiries($userId, $applyDateFilter),
            'myEnquiryTasks' => $this->agentModuleTasks($userId, Constants::MODULE_ENQUIRY, $applyDateFilter, $hasDateRange),
            'myListingTasks' => $this->agentModuleTasks($userId, Constants::MODULE_PROPERTY, $applyDateFilter, $hasDateRange),
            'priorityActions' => $this->priorityActions($userId, $applyDateFilter, 50),
            // Celebration popups scoped to the agent's OWN record: their latest
            // closed deal, their own new leads today, and their birthday greeting.
            'popups' => $this->dashboardPopups([$userId], $userId),
        ];
    }

    protected function teamOverview(?int $teamId = null, ?string $startDate = null, ?string $endDate = null): array
    {
        $agents = User::with(['role:id,name', 'teams:id,name'])
            ->where('status', 'active')
            ->whereHas('role', fn ($q) => $q->where('name', 'Agent'))
            ->when($teamId, fn ($q) => $q->whereHas('teams', fn ($t) => $t->where('teams.id', $teamId)))
            ->orderBy('name')
            ->get();

        $closingsCounts = $this->closedClosingsCountByAgent($agents->pluck('id')->all(), $startDate, $endDate);

        return $agents->map(fn ($u) => [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'agentCode' => $u->agentCode,
            'role' => $u->role?->name,
            'isAgent' => $u->role?->name === Constants::AGENT,
            'profilePhoto' => $u->profilePhoto,
            'isAvailable' => (bool) $u->isAvailable,
            'closingsClosed' => (int) ($closingsCounts[$u->id] ?? 0),
            'teams' => $u->teams->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->name,
            ])->values(),
        ])->values()->all();
    }

    protected function closedClosingsCountByAgent(array $agentIds, ?string $startDate, ?string $endDate): Collection
    {
        if (empty($agentIds)) {
            return collect();
        }

        return Closing::query()
            ->whereIn('status', [Constants::CLOSE_STATUS_OPEN, Constants::CLOSE_STATUS_CLOSED])
            ->whereIn('assignedAgentId', $agentIds)
            ->when($startDate, fn ($q) => $q->where('created_at', '>=', localDayStartUtc($startDate)))
            ->when($endDate, fn ($q) => $q->where('created_at', '<=', localDayEndUtc($endDate)))
            ->groupBy('assignedAgentId')
            ->selectRaw('assignedAgentId, COUNT(*) as cnt')
            ->pluck('cnt', 'assignedAgentId');
    }

    protected function admins(): int
    {
        return User::where('status', 'active')
            ->whereHas('role', fn ($q) => $q->whereNotIn('name', [Constants::AGENT, Constants::MANAGER, Constants::HR]))
            ->count();
    }

    protected function managers(): int
    {
        return User::where('status', 'active')
            ->whereHas('role', fn ($q) => $q->where('name', Constants::MANAGER))
            ->count();
    }

    protected function agents(): int
    {
        return User::where('status', 'active')
            ->whereHas('role', fn ($q) => $q->where('name', Constants::AGENT))
            ->count();
    }

    protected function totalLeadsByScore(callable $applyDateFilter): array
    {
        $totals = [
            Constants::LEAD_SCORE_HOT => 0,
            Constants::LEAD_SCORE_WARM => 0,
            Constants::LEAD_SCORE_COLD => 0,
        ];

        $counts = Enquiry::query()
            ->tap($applyDateFilter)
            ->whereIn('leadRating', array_keys($totals))
            ->selectRaw('leadRating, COUNT(*) as cnt')
            ->groupBy('leadRating')
            ->pluck('cnt', 'leadRating');

        foreach ($counts as $rating => $cnt) {
            $key = strtolower(trim((string) $rating));
            if (isset($totals[$key])) {
                $totals[$key] += (int) $cnt;
            }
        }

        return $totals;
    }

    protected function agentModuleTasks(int $userId, string $module, ?callable $applyDateFilter = null, bool $hasDateRange = false): array
    {
        $today = Carbon::today()->toDateString();
        $cutoff = Carbon::now()->endOfMonth()->toDateString();

        $query = Task::where('assignedTo', $userId)
            ->where('module', $module)
            ->whereNotIn('status', Constants::TASK_CLOSED_STATUSES)
            ->when(! $hasDateRange, fn ($q) => $q->whereBetween('dueDate', [$today, $cutoff]))
            ->when($applyDateFilter !== null, fn ($q) => $applyDateFilter($q, 'dueDate'))
            ->with(['assignedUser', 'creator'])
            ->orderBy('dueDate', 'asc');

        return [
            'count' => $query->count(),
            'data' => TaskResource::collection($query->limit(50)->get()),
        ];
    }

    protected function agentTodaysTasks(int $userId): array
    {
        $query = Task::where('assignedTo', $userId)
            ->where('dueDate', Carbon::today()->toDateString())
            ->whereNotIn('status', Constants::TASK_CLOSED_STATUSES)
            ->with(['assignedUser', 'creator'])
            ->orderBy('dueDate');

        return [
            'count' => $query->count(),
            'data' => TaskResource::collection($query->limit(50)->get()),
        ];
    }

    protected function agentOverdueTasks(int $userId): array
    {
        $query = Task::where('assignedTo', $userId)
            ->where('dueDate', '<', Carbon::today()->toDateString())
            ->whereNotIn('status', Constants::TASK_CLOSED_STATUSES)
            ->with(['assignedUser', 'creator'])
            ->orderBy('dueDate', 'desc');

        return [
            'count' => $query->count(),
            'data' => TaskResource::collection($query->limit(50)->get()),
        ];
    }

    /**
     * Overdue follow-ups (same set as agentOverdueTasks) but paginated for the
     * Agent View tab. `count` is the full total; `data` is the requested page;
     * `pagination` carries the paging meta. Defaults to page 1 / 50 per page, so
     * with no paging params the payload matches the old top-50 behaviour.
     *
     * The frontend tables send snake_case `per_page` app-wide; `perPage` is also
     * accepted. perPage is capped at 100.
     */
    protected function agentOverdueFollowUps(int $userId, Request $request): array
    {
        $perPage = min(100, max(1, (int) ($request->query('perPage') ?? $request->query('per_page') ?? 50)));
        $page = max(1, (int) $request->query('page', 1));

        $query = Task::where('assignedTo', $userId)
            ->where('dueDate', '<', Carbon::today()->toDateString())
            ->whereNotIn('status', Constants::TASK_CLOSED_STATUSES)
            ->with(['assignedUser', 'creator'])
            ->orderBy('dueDate', 'desc');

        $total = $query->count();

        return [
            'count' => $total,
            'data' => TaskResource::collection($query->forPage($page, $perPage)->get()),
            'pagination' => [
                'page' => $page,
                'perPage' => $perPage,
                'total' => $total,
                'totalPages' => (int) ceil($total / $perPage),
            ],
        ];
    }

    protected function agentHotEnquiries(int $userId, ?callable $applyDateFilter = null): array
    {
        // Hot = the effective lead score (leadRating), so manual overrides count
        // and this stays consistent with the dashboard's hot/warm/cold totals.
        $query = Enquiry::where('agentId', $userId)
            ->where('isOpenGame', 0)
            ->where('leadRating', Constants::LEAD_SCORE_HOT)
            ->when($applyDateFilter, fn ($q) => $applyDateFilter($q))
            ->with(['contact:id,name,email,phone', 'agent:id,name', 'listing:id,title,referenceNumber', 'communityInfo:id,name', 'cityInfo:id,name', 'community2Info:id,name', 'community3Info:id,name'])
            ->orderByDesc('updated_at');

        return [
            'count' => $query->count(),
            'data' => GetAllEnquiryResource::collection($query->limit(50)->get()),
        ];
    }

    protected function openGameEnquiries(int $userId, ?callable $applyDateFilter = null): array
    {
        $query = Enquiry::where('isOpenGame', 1)
            ->where('agentId', $userId)
            ->whereIn('status', Constants::ENQUIRY_ACTIVE_STATUSES)
            ->when($applyDateFilter, fn ($q) => $applyDateFilter($q))
            ->with(['contact:id,name,email,phone', 'agent:id,name', 'listing:id,title,referenceNumber', 'communityInfo:id,name', 'cityInfo:id,name', 'community2Info:id,name', 'community3Info:id,name'])
            ->orderByDesc('created_at');

        return [
            'count' => $query->count(),
            'data' => GetAllEnquiryResource::collection($query->limit(50)->get()),
        ];
    }

    protected function agentNewEnquiries(int $userId, ?callable $applyDateFilter = null): array
    {
        $query = Enquiry::where('agentId', $userId)
            ->where('isOpenGame', 0)
            ->where('status', Constants::ENQUIRY_NEW_LEAD)
            ->when($applyDateFilter, fn ($q) => $applyDateFilter($q))
            ->with(['contact:id,name,email,phone', 'agent:id,name', 'listing:id,title,referenceNumber', 'communityInfo:id,name', 'cityInfo:id,name', 'community2Info:id,name', 'community3Info:id,name'])
            ->orderByDesc('created_at');

        return [
            'count' => $query->count(),
            'data' => GetAllEnquiryResource::collection($query->limit(50)->get()),
        ];
    }

    protected function agentWeeklyViewingsMeetings(int $userId): array
    {
        $weekStart = Carbon::now()->startOfWeek();
        $weekEnd = Carbon::now()->endOfWeek();

        $counts = Task::query()
            ->where('assignedTo', $userId)
            ->whereIn('type', ['viewing', 'meeting'])
            ->whereBetween('created_at', [$weekStart, $weekEnd])
            ->selectRaw('type, COUNT(*) as cnt')
            ->groupBy('type')
            ->pluck('cnt', 'type');

        $viewings = (int) ($counts['viewing'] ?? 0);
        $meetings = (int) ($counts['meeting'] ?? 0);
        $total = $viewings + $meetings;

        $target = 10;
        $status = $total <= 3 ? 'red' : ($total <= 6 ? 'amber' : 'green');

        return [
            'count' => $total,
            'target' => $target,
            'status' => $status,
            'breakdown' => [
                'viewings' => $viewings,
                'meetings' => $meetings,
            ],
            'weekStart' => $weekStart->toDateString(),
            'weekEnd' => $weekEnd->toDateString(),
        ];
    }

    protected function usersLoggedInLast7Days(?string $startDate = null, ?string $endDate = null, ?array $userIds = null): array
    {
        $sevenDaysAgo = Carbon::now()->subDays(7);

        $recentLogins = LoginHistory::from('login_histories as lh')
            ->select('lh.userId', 'lh.loginAt as lastLoginAt', 'lh.loginType as lastLoginType')
            ->joinSub(
                LoginHistory::select('userId', DB::raw('MAX(loginAt) as lastLoginAt'))
                    ->where('loginAt', '>=', $sevenDaysAgo)
                    ->when($userIds !== null, fn ($q) => $q->whereIn('userId', $userIds))
                    ->groupBy('userId'),
                'latest',
                fn ($join) => $join->on('lh.userId', '=', 'latest.userId')
                    ->on('lh.loginAt', '=', 'latest.lastLoginAt')
            )
            ->orderByDesc('lh.loginAt')
            ->get();

        $loggedInUsers = User::with('role:id,name')
            ->whereIn('id', $recentLogins->pluck('userId'))
            ->get()
            ->keyBy('id');

        $closingsCounts = $this->closedClosingsCountByAgent($recentLogins->pluck('userId')->all(), $startDate, $endDate);

        $rows = $recentLogins->map(function ($row) use ($loggedInUsers, $closingsCounts) {
            $u = $loggedInUsers->get($row->userId);
            if (! $u) {
                return null;
            }

            return [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $u->role?->name,
                'isAgent' => $u->role?->name === Constants::AGENT,
                'profilePhoto' => $u->profilePhoto,
                'lastLoginAt' => convertToTimezone($row->lastLoginAt),
                'lastLoginSource' => $row->lastLoginType,
                'closingsClosed' => (int) ($closingsCounts[$u->id] ?? 0),
            ];
        })->filter()->values();

        return [
            'count' => $rows->count(),
            'data' => $rows,
        ];
    }

    public function leaderboard(Request $request): array
    {
        $period = $request->query('leaderBoardPeriod');
        if ($period === 'weekly') {
            $from = Carbon::now()->startOfWeek();
            $to = Carbon::now()->endOfWeek();
        } elseif ($period === 'quarter') {
            $from = Carbon::now()->startOfQuarter();
            $to = Carbon::now()->endOfQuarter();
        } elseif ($period === 'monthly') {
            $from = Carbon::now()->startOfMonth();
            $to = Carbon::now()->endOfMonth();
        } elseif ($period === 'yearly') {
            $from = Carbon::now()->startOfYear();
            $to = Carbon::now()->endOfYear();
        } else {
            $from = $request->query('leaderboardFrom')
                ? Carbon::parse($request->query('leaderboardFrom'))->startOfDay()
                : Carbon::now()->startOfMonth();
            $to = $request->query('leaderboardTo')
                ? Carbon::parse($request->query('leaderboardTo'))->endOfDay()
                : Carbon::now()->endOfMonth();
        }

        $teamId = $request->query('leaderBoardTeamId');
        $teamAgentIds = $teamId
            ? User::whereHas('teams', fn ($t) => $t->where('teams.id', $teamId))->pluck('id')->all()
            : null;

        $rows = Closing::select(
            'assignedAgentId',
            DB::raw('COUNT(*) as deals'),
            DB::raw('COALESCE(SUM(totalCommission), 0) as dealValue')
        )
            ->whereIn('status', [Constants::CLOSE_STATUS_OPEN, Constants::CLOSE_STATUS_CLOSED])
            ->whereBetween('created_at', [$from->toDateString(), $to->toDateString()])
            ->whereNotNull('assignedAgentId')
            ->when($teamAgentIds !== null, fn ($q) => $q->whereIn('assignedAgentId', $teamAgentIds))
            ->groupBy('assignedAgentId')
            ->get();

        $agents = User::with('role:id,name')
            ->whereIn('id', $rows->pluck('assignedAgentId'))
            ->get()
            ->keyBy('id');

        $base = $rows->map(function ($row) use ($agents) {
            $a = $agents->get($row->assignedAgentId);
            if (! $a) {
                return null;
            }

            return [
                'id' => $a->id,
                'name' => $a->name,
                'role' => $a->role?->name,
                'isAgent' => $a->role?->name === Constants::AGENT,
                'profilePhoto' => $a->profilePhoto,
                'isAvailable' => (bool) $a->isAvailable,
                'deals' => (int) $row->deals,
                'dealValue' => (float) $row->dealValue,
            ];
        })->filter();

        $byDeals = $base->sortByDesc(fn ($r) => [$r['deals'], $r['dealValue']])->values();
        $byRevenue = $base->sortByDesc(fn ($r) => [$r['dealValue'], $r['deals']])->values();

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'byDeals' => $byDeals,
            'byRevenue' => $byRevenue,
        ];
    }

    protected function overview(Request $request, ?array $scopeIds = null): array
    {
        $from = $request->query('overviewFrom')
            ? Carbon::parse($request->query('overviewFrom'))->startOfDay()
            : Carbon::now()->startOfYear();
        $to = $request->query('overviewTo')
            ? Carbon::parse($request->query('overviewTo'))->endOfDay()
            : Carbon::now()->endOfYear();

        $spanDays = $from->diffInDays($to) + 1;
        $useMonthlyBuckets = $spanDays > 62;

        $labels = [];
        $bucketKeys = [];

        if ($useMonthlyBuckets) {
            $bucketExpr = "DATE_FORMAT(%s, '%%Y-%%m')";
            $cursor = (clone $from)->startOfMonth();
            $endCursor = (clone $to)->startOfMonth();
            while ($cursor->lte($endCursor)) {
                $labels[] = $cursor->format('M Y');
                $bucketKeys[] = $cursor->format('Y-m');
                $cursor->addMonth();
            }
        } else {
            $bucketExpr = 'DATE(%s)';
            $cursor = (clone $from)->startOfDay();
            $endCursor = (clone $to)->startOfDay();
            while ($cursor->lte($endCursor)) {
                $labels[] = $cursor->format('j M');
                $bucketKeys[] = $cursor->toDateString();
                $cursor->addDay();
            }
        }

        $inquiryBuckets = Enquiry::query()
            ->whereBetween('created_at', [$from, $to])
            ->when($scopeIds !== null, fn ($q) => $q->whereIn('agentId', $scopeIds))
            ->selectRaw(sprintf($bucketExpr, 'created_at').' as bucket, COUNT(*) as cnt')
            ->groupBy('bucket')
            ->pluck('cnt', 'bucket');

        $dealBuckets = Closing::query()
            ->whereIn('status', [Constants::CLOSE_STATUS_OPEN, Constants::CLOSE_STATUS_CLOSED])
            ->whereBetween('created_at', [$from->toDateString(), $to->toDateString()])
            ->when($scopeIds !== null, fn ($q) => $q->whereIn('assignedAgentId', $scopeIds))
            ->selectRaw(sprintf($bucketExpr, 'created_at').' as bucket, COUNT(*) as cnt')
            ->groupBy('bucket')
            ->pluck('cnt', 'bucket');

        $inquiries = [];
        $deals = [];
        foreach ($bucketKeys as $key) {
            $inquiries[] = (int) ($inquiryBuckets[$key] ?? 0);
            $deals[] = (int) ($dealBuckets[$key] ?? 0);
        }

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'granularity' => $useMonthlyBuckets ? 'month' : 'day',
            'labels' => $labels,
            'inquiries' => $inquiries,
            'deals' => $deals,
        ];
    }

    /**
     * @param  int|array<int, int>|null  $userId  A single agent id, or an array of agent
     *                                            ids (manager team scope), or null (admin/global).
     */
    protected function priorityActions($userId = null, ?callable $applyDateFilter = null, int $limit = 10): array
    {

        // True when scoping to one agent or a team (an empty array still scopes — to nothing).
        $hasScope = is_array($userId) ? true : (bool) $userId;
        $applyOwner = function ($q, string $column) use ($userId) {
            if (is_array($userId)) {
                $q->whereIn($column, $userId);
            } elseif ($userId) {
                $q->where($column, $userId);
            }
        };

        // Format a min/max requirement (beds, baths, price, size) into a single label:
        // "2" when min==max, "1–3" for a range, or just the side that is set.
        $range = function ($min, $max) {
            $min = ($min === null || $min === '') ? null : $min;
            $max = ($max === null || $max === '') ? null : $max;
            if ($min === null && $max === null) {
                return null;
            }
            if ($min !== null && $max !== null) {
                return $min == $max ? (string) $min : $min.'–'.$max;
            }

            return (string) ($min ?? $max);
        };

        $enquiryBase = function () use ($hasScope, $applyDateFilter, $applyOwner) {
            // listing is eager-loaded so cards can show the enquired listing's reference + title.
            $q = Enquiry::with(['contact:id,name', 'communityInfo:id,name', 'listing:id,referenceNumber,title']);
            if ($hasScope) {
                $applyOwner($q, 'agentId');
                $q->where('isOpenGame', 0);
            }
            if ($applyDateFilter) {
                $applyDateFilter($q);
            }

            return $q;
        };

        $newOpenLeads = Enquiry::with(['contact:id,name', 'communityInfo:id,name', 'listing:id,referenceNumber,title'])
            ->where('isOpenGame', 1)
            ->whereIn('status', Constants::ENQUIRY_ACTIVE_STATUSES)
            ->when($hasScope, fn ($q) => $applyOwner($q, 'agentId'))
            ->when($applyDateFilter !== null, fn ($q) => $applyDateFilter($q))
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn ($e) => [
                'id' => $e->id,
                'referenceNo' => $e->referenceNo,
                'contact' => ['name' => $e->contact?->masterOrSelf()->name],
                'communityLabel' => $e->communityInfo?->name,
                'listingReference' => $e->listing?->referenceNumber,
                'listingTitle' => $e->listing?->title,
                'bedrooms' => $range($e->bedroomsMin, $e->bedroomsMax),
                'bathrooms' => $range(formatDecimal($e->bathroomsMin), formatDecimal($e->bathroomsMax)),
                'priceMin' => $e->priceMin,
                'priceMax' => $e->priceMax,
                'budget' => $e->budget ?: ($e->priceMax ?: $e->priceMin),
                'leadSource' => Constants::leadSourceGroupLabel($e->leadSource),
                'createdAt' => convertToTimezone($e->created_at),
                'status' => $e->status ? (Constants::enquiryStatusMap($e->enquiryType)[$e->status] ?? $e->status) : null,
            ]);

        // Hot = effective lead score (leadRating) so manual overrides are honoured
        // and this matches the hot/warm/cold totals shown elsewhere.
        $hotLeads = $enquiryBase()
            ->where('leadRating', Constants::LEAD_SCORE_HOT)
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get()
            ->map(fn ($e) => [
                'id' => $e->id,
                'referenceNo' => $e->referenceNo,
                'contact' => ['name' => $e->contact?->masterOrSelf()->name],
                'communityLabel' => $e->communityInfo?->name,
                'listingReference' => $e->listing?->referenceNumber,
                'listingTitle' => $e->listing?->title,
                'bedrooms' => $range($e->bedroomsMin, $e->bedroomsMax),
                'bathrooms' => $range(formatDecimal($e->bathroomsMin), formatDecimal($e->bathroomsMax)),
                'priceMin' => $e->priceMin,
                'priceMax' => $e->priceMax,
                'budget' => $e->budget ?: ($e->priceMax ?: $e->priceMin),
                'lastActivityDate' => convertToTimezone($e->updated_at),
                'leadScore' => 'Hot',
            ]);

        $pendingApprovals = Property::query()
            ->where('approvalStatus', Constants::APPROVAL_REQUESTED)
            ->when($hasScope, fn ($q) => $applyOwner($q, 'agentId'))
            ->when($applyDateFilter, fn ($q) => $applyDateFilter($q))
            ->with('agent:id,name')
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'referenceNumber' => $p->referenceNumber,
                'title' => $p->title,
                'agentDetails' => ['name' => $p->agent?->name],
                'listingType' => $p->listingType ? ucfirst($p->listingType) : null,
                'bedrooms' => $p->bedrooms,
                'bathrooms' => formatDecimal($p->bathrooms),
                'price' => $p->price,
                'updated_at' => convertToTimezone($p->updated_at),
                'approvalStatus' => Constants::PROPERTY_APPROVAL_STATUS[$p->approvalStatus] ?? $p->approvalStatus,
            ]);

        return [
            'newOpenLeads' => $newOpenLeads,
            'hotLeads' => $hotLeads,
            'pendingApprovals' => $pendingApprovals,
        ];
    }

    public function agentMonitoring(Request $request, int $agentId): array
    {
        $agent = User::find($agentId);

        $startDate = $request->query('startDate');
        $endDate = $request->query('endDate');

        $now = Carbon::now();
        $current = $this->collectAgentMonitoringStats(
            $agentId,
            $startDate,
            $endDate,
            $now->copy()->startOfWeek()->toDateString(),
            $now->copy()->endOfWeek()->toDateString(),
            $now->copy()->startOfMonth()->toDateString(),
            $now->copy()->endOfMonth()->toDateString(),
        );

        $previous = null;
        if ($startDate && $endDate) {
            $start = Carbon::parse($startDate)->startOfDay();
            $end = Carbon::parse($endDate)->startOfDay();
            $lengthDays = $start->diffInDays($end) + 1;
            $prevEnd = $start->copy()->subDay();
            $prevStart = $prevEnd->copy()->subDays($lengthDays - 1);

            $prevWeekRef = $now->copy()->subWeek();
            $prevMonthRef = $now->copy()->subMonthNoOverflow();
            $previous = $this->collectAgentMonitoringStats(
                $agentId,
                $prevStart->toDateString(),
                $prevEnd->toDateString(),
                $prevWeekRef->copy()->startOfWeek()->toDateString(),
                $prevWeekRef->copy()->endOfWeek()->toDateString(),
                $prevMonthRef->copy()->startOfMonth()->toDateString(),
                $prevMonthRef->copy()->endOfMonth()->toDateString(),
            );
        }

        return [
            'agentId' => $agentId,
            'agentName' => $agent?->name,
            'agentType' => $agent?->agentType,
            'from' => $startDate,
            'to' => $endDate,
            ...$current,
            'previous' => $previous,
        ];
    }

    protected function collectAgentMonitoringStats(
        int $agentId,
        ?string $startDate,
        ?string $endDate,
        string $weekStart,
        string $weekEnd,
        string $monthStart,
        string $monthEnd,
    ): array {
        // created_at is a UTC datetime, so the selected local day must be
        // converted to UTC boundaries. dueDate is a plain DATE column and is
        // compared against the calendar date as-is (no timezone shift).
        $applyDateFilter = function ($query, string $column = 'created_at') use ($startDate, $endDate) {
            $isDateColumn = $column === 'dueDate';
            if ($startDate) {
                $query->where($column, '>=', $isDateColumn ? $startDate.' 00:00:00' : localDayStartUtc($startDate));
            }
            if ($endDate) {
                $query->where($column, '<=', $isDateColumn ? $endDate.' 23:59:59' : localDayEndUtc($endDate));
            }
        };

        $closedEnquiryStatuses = [Constants::ENQUIRY_SOLD, Constants::ENQUIRY_RENTED];
        $deadEnquiryStatuses = [Constants::ENQUIRY_LOST];

        // Calls = completed call activities PLUS calls logged directly on a
        // contact. Agents often log a call on the contact before it becomes an
        // enquiry, and those efforts must count toward KPIs too. Every call task
        // already writes a mirror contact note tagged with its taskId, so we add
        // only untagged contact notes (taskId null) to avoid double counting.
        $callsMadeLogged = Task::query()
            ->where('assignedTo', $agentId)
            ->where('type', 'call')
            ->whereIn('status', Constants::TASK_CLOSED_STATUSES)
            ->tap(fn ($q) => $applyDateFilter($q, 'dueDate'))
            ->count()
            + ContactNote::query()
                ->where('createdBy', $agentId)
                ->where('type', 'call')
                ->whereNull('taskId')
                ->tap(fn ($q) => $applyDateFilter($q, 'created_at'))
                ->count();

        $inquiriesReceived = Enquiry::query()
            ->where('agentId', $agentId)
            ->where('isOpenGame', 0)
            ->whereIn('status', Constants::ENQUIRY_ACTIVE_STATUSES)
            ->tap(fn ($q) => $applyDateFilter($q))
            ->count();

        // Total activity roll-up (client's rule): every completed activity counts
        // here — calls, meetings, viewings, WhatsApp, emails and generic
        // follow-ups. Only notes are excluded (record keeping, not activity), so
        // a new activity type is included automatically.
        //
        // This deliberately overlaps the Calls and Viewings/Meetings KPIs: the
        // client wants one "total activity" figure, and restricting it to a
        // subset left the box empty and misrepresented active agents. The
        // no-double-count rule still holds WITHIN each KPI — a call is counted
        // once here and once under Calls, never twice in either.
        $tasksFollowupsCompleted = Task::query()
            ->where('assignedTo', $agentId)
            ->whereIn('status', Constants::TASK_CLOSED_STATUSES)
            ->where(fn ($q) => $q->whereNull('type')
                ->orWhereNotIn('type', ['note']))
            ->tap(fn ($q) => $applyDateFilter($q, 'dueDate'))
            ->count();

        // Avg minutes between enquiry created_at and agent's first history entry for it.
        $firstHistorySub = DB::table('enquiry_histories')
            ->selectRaw('enquiryId, MIN(created_at) as firstAt')
            ->where('userId', $agentId)
            ->groupBy('enquiryId');

        $avgResponseRaw = DB::table('enquiries as e')
            ->joinSub($firstHistorySub, 'fa', fn ($j) => $j->on('fa.enquiryId', '=', 'e.id'))
            ->where('e.agentId', $agentId)
            ->where('e.isOpenGame', 0)
            ->when($startDate, fn ($q) => $q->where('e.created_at', '>=', $startDate.' 00:00:00'))
            ->when($endDate, fn ($q) => $q->where('e.created_at', '<=', $endDate.' 23:59:59'))
            ->value(DB::raw('AVG(TIMESTAMPDIFF(MINUTE, e.created_at, fa.firstAt))'));

        $avgResponseTimeMins = $avgResponseRaw !== null ? (int) round((float) $avgResponseRaw) : null;

        $enquiryByStatus = Enquiry::query()
            ->where('agentId', $agentId)
            ->where('isOpenGame', 0)
            ->tap(fn ($q) => $applyDateFilter($q))
            ->selectRaw('status, COUNT(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status');

        $pipelineCount = function (array $statuses) use ($enquiryByStatus) {
            $total = 0;
            foreach ($statuses as $status) {
                $total += (int) ($enquiryByStatus[$status] ?? 0);
            }

            return $total;
        };

        $listingBase = fn () => Property::query()
            ->where('agentId', $agentId)
            ->whereIn('status', Constants::MATCHING_ALLOWED_STATUSES);

        $liveListings = $listingBase()->tap(fn ($q) => $applyDateFilter($q))->count();
        $exclusiveListings = $listingBase()
            ->where('managedType', Constants::MANAGED_TYPE_EXCLUSIVE)
            ->tap(fn ($q) => $applyDateFilter($q))
            ->count();

        $viewingBase = fn () => Task::query()
            ->where('assignedTo', $agentId)
            ->where('type', 'viewing')
            ->tap(fn ($q) => $applyDateFilter($q, 'dueDate'));

        $viewingsBooked = $viewingBase()->count();
        $viewingsCompleted = (clone $viewingBase())
            ->whereIn('status', Constants::TASK_CLOSED_STATUSES)
            ->count();
        $viewingsNoShows = (clone $viewingBase())
            ->whereNotIn('status', Constants::TASK_CLOSED_STATUSES)
            ->where('dueDate', '<', Carbon::today()->toDateString())
            ->count();

        $closedDealsBase = fn () => Closing::query()
            ->where('assignedAgentId', $agentId)
            ->whereIn('status', [Constants::CLOSE_STATUS_OPEN, Constants::CLOSE_STATUS_CLOSED]);

        $weeklyClosed = (clone $closedDealsBase())
            ->whereBetween('created_at', [$weekStart, $weekEnd])
            ->count();

        $monthlyClosed = (clone $closedDealsBase())
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->count();

        // Gross commission = the deal's total commission (not the agent's split,
        // which is often left blank). Matches every other commission metric.
        $grossCommission = (float) $closedDealsBase()
            ->tap(fn ($q) => $applyDateFilter($q, 'created_at'))
            ->sum('totalCommission');

        return [
            'activities' => [
                'callsMadeLogged' => $callsMadeLogged,
                'inquiriesReceived' => $inquiriesReceived,
                'tasksFollowupsCompleted' => $tasksFollowupsCompleted,
                'avgResponseTimeMins' => $avgResponseTimeMins,
            ],
            'pipeline' => [
                'active' => $pipelineCount(Constants::ENQUIRY_ACTIVE_STATUSES),
                'closed' => $pipelineCount($closedEnquiryStatuses),
                'dead' => $pipelineCount($deadEnquiryStatuses),
            ],
            'listings' => [
                'live' => $liveListings,
                'exclusive' => $exclusiveListings,
            ],
            'viewings' => [
                'booked' => $viewingsBooked,
                'completed' => $viewingsCompleted,
                'noShows' => $viewingsNoShows,
            ],
            'deals' => [
                'weeklyClosed' => $weeklyClosed,
                'monthlyClosed' => $monthlyClosed,
                'grossCommission' => $grossCommission,
            ],
        ];
    }

    /**
     * Per-agent actuals for a date range, keyed to match the _targetKeys metric names.
     * Used by the scheduled target-performance email/alerts.
     */
    public function agentTargetActuals(int $agentId, string $startDate, string $endDate): array
    {
        $between = fn ($q, string $column = 'created_at') => $q
            ->where($column, '>=', $startDate.' 00:00:00')
            ->where($column, '<=', $endDate.' 23:59:59');

        // Completed call activities only — notes do not count toward KPIs.
        $calls = Task::query()
            ->where('assignedTo', $agentId)
            ->where('type', 'call')
            ->whereIn('status', Constants::TASK_CLOSED_STATUSES)
            ->tap(fn ($q) => $between($q, 'dueDate'))
            ->count();

        // Only completed viewings/meetings register (open/pending excluded).
        $viewingsMeetings = Task::query()
            ->where('assignedTo', $agentId)
            ->whereIn('type', ['viewing', 'meeting'])
            ->whereIn('status', Constants::TASK_CLOSED_STATUSES)
            ->tap(fn ($q) => $between($q, 'dueDate'))
            ->count();

        $inquiriesReceived = Enquiry::query()
            ->where('agentId', $agentId)
            ->where('isOpenGame', 0)
            ->whereIn('status', Constants::ENQUIRY_ACTIVE_STATUSES)
            ->tap(fn ($q) => $between($q))
            ->count();

        // Total activity roll-up: every completed activity except notes.
        // Overlaps the Calls / Viewings-Meetings KPIs by design.
        $tasksCompleted = Task::query()
            ->where('assignedTo', $agentId)
            ->whereIn('status', Constants::TASK_CLOSED_STATUSES)
            ->where(fn ($q) => $q->whereNull('type')
                ->orWhereNotIn('type', ['note']))
            ->tap(fn ($q) => $between($q))
            ->count();

        $dealsBase = fn () => Closing::query()
            ->where('assignedAgentId', $agentId)
            ->whereIn('status', [Constants::CLOSE_STATUS_OPEN, Constants::CLOSE_STATUS_CLOSED])
            ->tap(fn ($q) => $between($q));

        $dealsSales = $dealsBase()->where('listingType', Constants::CLOSE_LISTING_TYPE_SALE)->count();
        $dealsLeasing = $dealsBase()->where('listingType', Constants::CLOSE_LISTING_TYPE_RENT)->count();
        $offPlan = $dealsBase()
            ->whereNotIn('listingType', [Constants::CLOSE_LISTING_TYPE_SALE, Constants::CLOSE_LISTING_TYPE_RENT])
            ->count();
        $revenue = (float) $dealsBase()->sum('totalCommission');

        $listingBase = fn () => Property::query()
            ->where('agentId', $agentId)
            ->whereIn('status', Constants::MATCHING_ALLOWED_STATUSES)
            ->tap(fn ($q) => $between($q));

        $listingsForSale = $listingBase()->where('listingType', Constants::PROPERTY_PURPOSE_SALE)->count();
        $listingsForLease = $listingBase()->where('listingType', Constants::PROPERTY_PURPOSE_RENT)->count();

        return [
            'calls' => $calls,
            'viewingsMeetings' => $viewingsMeetings,
            'inquiriesReceived' => $inquiriesReceived,
            'tasksCompleted' => $tasksCompleted,
            'dealsSales' => $dealsSales,
            'dealsLeasing' => $dealsLeasing,
            'offPlan' => $offPlan,
            'listingsForSale60d' => $listingsForSale,
            'listingsForLease30d' => $listingsForLease,
            'revenue' => $revenue,
        ];
    }

    public function agentPerformanceCharts(Request $request): array
    {
        $userId = (int) ($request->query('agent') ?: user()->id);
        $from = Carbon::parse($request->input('startDate'))->startOfDay();
        $to = Carbon::parse($request->input('endDate'))->endOfDay();

        $agentType = User::where('id', $userId)->value('agentType');

        return [
            'agentId' => $userId,
            'agentType' => $agentType,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'viewingsMeetingsWeekly' => $this->agentWeeklyViewingsMeetings($userId),
            'charts' => [
                // Completed call activities PLUS calls logged directly on a
                // contact (taskId null so mirror notes are not double counted).
                'callsMadeLogged' => Task::query()
                    ->where('assignedTo', $userId)
                    ->where('type', 'call')
                    ->whereIn('status', Constants::TASK_CLOSED_STATUSES)
                    ->whereBetween('dueDate', [$from->toDateString(), $to->toDateString()])
                    ->count()
                    + ContactNote::query()
                        ->where('createdBy', $userId)
                        ->where('type', 'call')
                        ->whereNull('taskId')
                        ->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
                        ->count(),
                'inquiryReceived' => Enquiry::query()
                    ->where('agentId', $userId)
                    ->where('isOpenGame', 0)
                    ->whereIn('status', Constants::ENQUIRY_ACTIVE_STATUSES)
                    ->whereBetween('created_at', [$from, $to])
                    ->count(),
                // Total activity roll-up: every completed activity except notes
                // (overlaps the Calls / Viewings-Meetings KPIs by design).
                'tasksFollowupsCompleted' => Task::query()
                    ->where('assignedTo', $userId)
                    ->whereBetween('dueDate', [$from->toDateString(), $to->toDateString()])
                    ->whereIn('status', Constants::TASK_CLOSED_STATUSES)
                    ->where(fn ($q) => $q->whereNull('type')
                        ->orWhereNotIn('type', ['note']))
                    ->count(),
                'dealsSales' => Closing::query()
                    ->where('assignedAgentId', $userId)
                    ->whereIn('status', [Constants::CLOSE_STATUS_OPEN, Constants::CLOSE_STATUS_CLOSED])
                    ->where('listingType', Constants::CLOSE_LISTING_TYPE_SALE)
                    ->whereBetween('created_at', [$from, $to])
                    ->count(),
                'dealsLeasing' => Closing::query()
                    ->where('assignedAgentId', $userId)
                    ->whereIn('status', [Constants::CLOSE_STATUS_OPEN, Constants::CLOSE_STATUS_CLOSED])
                    ->where('listingType', Constants::CLOSE_LISTING_TYPE_RENT)
                    ->whereBetween('created_at', [$from, $to])
                    ->count(),
                'offPlans' => Property::query()
                    ->where('agentId', $userId)
                    ->where('listingType', Constants::PROPERTY_PURPOSE_OFFPLAN)
                    ->where(fn ($q) => $q->where('completionStatus', '!=', 'offPlanPrimary')->orWhereNull('completionStatus'))
                    ->whereBetween('created_at', [$from, $to])
                    ->count(),
                'listingsForSale' => Property::query()
                    ->where('agentId', $userId)
                    ->where('listingType', Constants::PROPERTY_PURPOSE_SALE)
                    ->whereIn('status', [Constants::PROPERTY_STATUS_PUBLISHED, Constants::PROPERTY_STATUS_UNPUBLISHED, Constants::PROPERTY_STATUS_RENTED_SOLD])
                    ->whereBetween('created_at', [$from, $to])
                    ->count(),
                'listingsForLease' => Property::query()
                    ->where('agentId', $userId)
                    ->where('listingType', Constants::PROPERTY_PURPOSE_RENT)
                    ->whereIn('status', [Constants::PROPERTY_STATUS_PUBLISHED, Constants::PROPERTY_STATUS_UNPUBLISHED, Constants::PROPERTY_STATUS_RENTED_SOLD])
                    ->whereBetween('created_at', [$from, $to])
                    ->count(),
                'viewingsMeetings' => Task::query()
                    ->where('assignedTo', $userId)
                    ->whereIn('status', Constants::TASK_CLOSED_STATUSES)
                    ->whereBetween('dueDate', [$from->toDateString(), $to->toDateString()])
                    ->whereIn('type', ['viewing', 'meeting'])
                    ->count(),
                'revenueCommission' => (float) Closing::query()
                    ->where('assignedAgentId', $userId)
                    ->whereIn('status', [Constants::CLOSE_STATUS_OPEN, Constants::CLOSE_STATUS_CLOSED])
                    ->whereBetween('created_at', [$from, $to])
                    ->sum('totalCommission'),
            ],
        ];
    }
}
