<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\EmployeeDocument;
use App\Models\Leave;
use App\Models\User;
use App\Models\UserMeta;
use App\Traits\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HrDashboardController extends Controller
{
    use ApiResponse;

    /**
     * Get summary metrics and widgets for HR Dashboard
     */
    public function index()
    {
        try {
            $today = Carbon::today()->toDateString();
            $tomorrow = Carbon::tomorrow()->toDateString();

            // 1. Active Employees Count
            $activeEmployeesCount = User::where('status', 'active')->count();

            // 2. Employees on Leave Today
            $employeesOnLeaveToday = Leave::with('user:id,employee_id,name,department,designation,profilePhoto')
                ->where('status', Leave::STATUS_APPROVED)
                ->whereDate('start_date', '<=', $today)
                ->whereDate('end_date', '>=', $today)
                ->get()
                ->map(function ($leave) {
                    $startDate = $leave->start_date instanceof Carbon ? $leave->start_date->toDateString() : (string) $leave->start_date;
                    $endDate = $leave->end_date instanceof Carbon ? $leave->end_date->toDateString() : (string) $leave->end_date;

                    return [
                        'id'            => $leave->id,
                        'user_id'       => $leave->user_id,
                        'name'          => $leave->user?->name ?? $leave->staff_name,
                        'employee_id'   => $leave->user?->employee_id,
                        'department'    => $leave->user?->department,
                        'designation'   => $leave->user?->designation,
                        'leave_type'    => $leave->leave_type,
                        'start_date'    => $startDate,
                        'end_date'      => $endDate,
                        'working_days'  => $leave->working_days,
                        'reason'        => $leave->reason,
                        'profile_photo' => $leave->user?->profilePhoto,
                        'user'          => $leave->user,
                    ];
                });

            // 3. Upcoming Birthdays (Today & Tomorrow & next 7 days)
            $allActiveUsers = User::where('status', 'active')
                ->whereNotNull('date_of_birth')
                ->select('id', 'name', 'employee_id', 'department', 'date_of_birth', 'profilePhoto')
                ->get();

            $todayBirthdays = [];
            $tomorrowBirthdays = [];
            $upcomingBirthdays = [];

            $currentYear = Carbon::today()->year;

            foreach ($allActiveUsers as $user) {
                $dob = Carbon::parse($user->date_of_birth);
                $bdayThisYear = Carbon::create($currentYear, $dob->month, $dob->day)->startOfDay();

                // If birthday already passed this year, check next year
                if ($bdayThisYear->isPast() && !$bdayThisYear->isToday()) {
                    $bdayThisYear = Carbon::create($currentYear + 1, $dob->month, $dob->day)->startOfDay();
                }

                $diffDays = Carbon::today()->diffInDays($bdayThisYear, false);

                $bdayData = [
                    'id'            => $user->id,
                    'name'          => $user->name,
                    'employee_id'   => $user->employee_id,
                    'department'    => $user->department,
                    'birthday'      => $dob->format('d M'),
                    'profile_photo' => $user->profilePhoto,
                    'days_away'     => $diffDays,
                ];

                if ($diffDays === 0) {
                    $todayBirthdays[] = $bdayData;
                } elseif ($diffDays === 1) {
                    $tomorrowBirthdays[] = $bdayData;
                } elseif ($diffDays > 1 && $diffDays <= 7) {
                    $upcomingBirthdays[] = $bdayData;
                }
            }

            // 4. Expiring Documents Count in Next 30 Days (from user_meta & employee_documents)
            $thirtyDaysAhead = Carbon::today()->addDays(30)->toDateString();

            $metaDocsExpiring = UserMeta::whereIn('metaKey', [
                '_visaExpiryDate',
                '_passportExpiryDate',
                '_emiratesIdExpiryDate',
                '_insuranceExpiryDate',
                '_labourContractExpiryDate',
                '_labourCardExpiryDate',
            ])
            ->whereHas('user', fn ($q) => $q->where('status', 'active'))
            ->whereBetween('metaValue', [$today, $thirtyDaysAhead])
            ->count();

            // Check employee_documents
            $customDocsExpiring = EmployeeDocument::whereBetween('expiry_date', [$today, $thirtyDaysAhead])
                ->whereHas('user', fn ($q) => $q->where('status', 'active'))
                ->count();

            $expiringCount = $metaDocsExpiring + $customDocsExpiring;

            // 5. Pending Leave Requests Count & List
            $pendingLeaves = Leave::with('user:id,employee_id,name,department,designation,profilePhoto')
                ->where('status', Leave::STATUS_PENDING)
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get()
                ->map(function ($leave) {
                    $startDate = $leave->start_date instanceof Carbon ? $leave->start_date->toDateString() : (string) $leave->start_date;
                    $endDate = $leave->end_date instanceof Carbon ? $leave->end_date->toDateString() : (string) $leave->end_date;

                    return [
                        'id'              => $leave->id,
                        'user_id'         => $leave->user_id,
                        'name'            => $leave->user?->name ?? $leave->staff_name,
                        'employee_id'     => $leave->user?->employee_id,
                        'department'      => $leave->user?->department,
                        'designation'     => $leave->user?->designation,
                        'leave_type'      => $leave->leave_type,
                        'start_date'      => $startDate,
                        'end_date'        => $endDate,
                        'working_days'    => $leave->working_days,
                        'reason'          => $leave->reason,
                        'handover_to'     => $leave->handover_to,
                        'has_doctor_note' => $leave->has_doctor_note,
                        'attachment_url'  => $leave->attachment_url,
                        'profile_photo'   => $leave->user?->profilePhoto,
                        'user'            => $leave->user,
                        'created_at'      => $leave->created_at?->toIso8601String(),
                    ];
                });

            $pendingLeavesCount = Leave::where('status', Leave::STATUS_PENDING)->count();

            // 6. Daily Attendance Summary
            $attendanceCounts = Attendance::where('date', $today)
                ->select('status', DB::raw('count(*) as count'))
                ->groupBy('status')
                ->pluck('count', 'status');

            $presentCount  = (int) ($attendanceCounts[Attendance::STATUS_PRESENT] ?? 0);
            $absentCount   = (int) ($attendanceCounts[Attendance::STATUS_ABSENT] ?? 0);
            $halfDayCount  = (int) ($attendanceCounts[Attendance::STATUS_HALF_DAY] ?? 0);
            $onLeaveCount  = (int) ($attendanceCounts[Attendance::STATUS_ON_LEAVE] ?? 0);
            $markedTotal   = $presentCount + $absentCount + $halfDayCount + $onLeaveCount;
            $notMarkedCount = max(0, $activeEmployeesCount - $markedTotal);

            return $this->success([
                'active_employees' => $activeEmployeesCount,
                'employees_on_leave_today' => [
                    'count' => $employeesOnLeaveToday->count(),
                    'list'  => $employeesOnLeaveToday,
                ],
                'birthdays' => [
                    'today'    => $todayBirthdays,
                    'tomorrow' => $tomorrowBirthdays,
                    'upcoming' => $upcomingBirthdays,
                ],
                'expiring_documents_count' => $expiringCount,
                'pending_leave_requests' => [
                    'count' => $pendingLeavesCount,
                    'list'  => $pendingLeaves,
                ],
                'daily_attendance' => [
                    'date'       => $today,
                    'total'      => $activeEmployeesCount,
                    'present'    => $presentCount,
                    'absent'     => $absentCount,
                    'half_day'   => $halfDayCount,
                    'on_leave'   => $onLeaveCount,
                    'not_marked' => $notMarkedCount,
                ],
            ], 'HR Dashboard statistics retrieved successfully.');
        } catch (\Exception $e) {
            return $this->exceptionResponse('HrDashboardController : index', $e);
        }
    }

    /**
     * Monthly Birthday Calendar View
     */
    public function birthdayCalendar(Request $request)
    {
        try {
            $month = (int) ($request->get('month', Carbon::today()->month));
            $year  = (int) ($request->get('year', Carbon::today()->year));

            $users = User::where('status', 'active')
                ->whereNotNull('date_of_birth')
                ->whereMonth('date_of_birth', $month)
                ->select('id', 'name', 'employee_id', 'department', 'designation', 'date_of_birth', 'profilePhoto')
                ->get()
                ->map(function ($user) use ($year, $month) {
                    $dob = Carbon::parse($user->date_of_birth);
                    $eventDate = Carbon::create($year, $month, $dob->day)->toDateString();

                    return [
                        'user_id'       => $user->id,
                        'name'          => $user->name,
                        'employee_id'   => $user->employee_id,
                        'department'    => $user->department,
                        'designation'   => $user->designation,
                        'event_date'    => $eventDate,
                        'day'           => $dob->day,
                        'profile_photo' => $user->profilePhoto,
                    ];
                })
                ->sortBy('day')
                ->values();

            return $this->success($users, 'Birthday calendar retrieved successfully.');
        } catch (\Exception $e) {
            return $this->exceptionResponse('HrDashboardController : birthdayCalendar', $e);
        }
    }
}

