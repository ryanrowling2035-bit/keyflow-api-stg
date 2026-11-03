<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\User;
use App\Traits\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class HrAttendanceController extends Controller
{
    use ApiResponse;

    /**
     * Mark or update attendance for a single employee
     */
    public function markSingle(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id'        => 'required|exists:users,id',
            'date'           => 'required|date',
            'status'         => 'required|in:' . implode(',', Attendance::STATUSES),
            'check_in_time'  => 'nullable|date_format:H:i',
            'check_out_time' => 'nullable|date_format:H:i',
            'remarks'        => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 422, $validator->errors());
        }

        try {
            $markerId = auth('api')->id();

            $attendance = Attendance::updateOrCreate(
                [
                    'user_id' => $request->user_id,
                    'date'    => Carbon::parse($request->date)->toDateString(),
                ],
                [
                    'status'         => $request->status,
                    'check_in_time'  => $request->check_in_time,
                    'check_out_time' => $request->check_out_time,
                    'marked_by'      => $markerId,
                    'remarks'        => $request->remarks,
                ]
            );

            return $this->success($attendance->load('user:id,name,employee_id'), 'Attendance marked successfully.');
        } catch (\Exception $e) {
            return $this->exceptionResponse('HrAttendanceController : markSingle', $e);
        }
    }

    /**
     * Mark attendance in bulk (e.g. HR marks full team / department for a date)
     */
    public function markBulk(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'date'           => 'required|date',
            'records'        => 'required|array|min:1',
            'records.*.user_id' => 'required|exists:users,id',
            'records.*.status'  => 'required|in:' . implode(',', Attendance::STATUSES),
            'records.*.check_in_time'  => 'nullable|date_format:H:i',
            'records.*.check_out_time' => 'nullable|date_format:H:i',
            'records.*.remarks'        => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 422, $validator->errors());
        }

        try {
            DB::beginTransaction();

            $date = Carbon::parse($request->date)->toDateString();
            $markerId = auth('api')->id();
            $saved = [];

            foreach ($request->records as $item) {
                $att = Attendance::updateOrCreate(
                    [
                        'user_id' => $item['user_id'],
                        'date'    => $date,
                    ],
                    [
                        'status'         => $item['status'],
                        'check_in_time'  => $item['check_in_time'] ?? null,
                        'check_out_time' => $item['check_out_time'] ?? null,
                        'marked_by'      => $markerId,
                        'remarks'        => $item['remarks'] ?? null,
                    ]
                );
                $saved[] = $att;
            }

            DB::commit();

            return $this->success(['count' => count($saved)], 'Bulk attendance marked successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->exceptionResponse('HrAttendanceController : markBulk', $e);
        }
    }

    /**
     * Daily attendance summary for HR dashboard / Attendance page
     */
    public function dailySummary(Request $request)
    {
        try {
            $date = $request->filled('date') ? Carbon::parse($request->date)->toDateString() : Carbon::today()->toDateString();

            // Total active employees
            $totalActive = User::where('status', 'active')->count();

            // Status counts for this date
            $records = Attendance::where('date', $date)
                ->select('status', DB::raw('count(*) as count'))
                ->groupBy('status')
                ->pluck('count', 'status');

            $present  = (int) ($records[Attendance::STATUS_PRESENT] ?? 0);
            $absent   = (int) ($records[Attendance::STATUS_ABSENT] ?? 0);
            $halfDay  = (int) ($records[Attendance::STATUS_HALF_DAY] ?? 0);
            $onLeave  = (int) ($records[Attendance::STATUS_ON_LEAVE] ?? 0);
            $marked   = $present + $absent + $halfDay + $onLeave;
            $notMarked = max(0, $totalActive - $marked);

            return $this->success([
                'date'         => $date,
                'total_active' => $totalActive,
                'present'      => $present,
                'absent'       => $absent,
                'half_day'     => $halfDay,
                'on_leave'     => $onLeave,
                'not_marked'   => $notMarked,
            ], 'Daily attendance summary retrieved.');
        } catch (\Exception $e) {
            return $this->exceptionResponse('HrAttendanceController : dailySummary', $e);
        }
    }

    /**
     * Attendance Report
     * Filters: user_id, department, date_from, date_to, status
     */
    public function report(Request $request)
    {
        try {
            $dateFrom = $request->filled('date_from') ? Carbon::parse($request->date_from)->toDateString() : Carbon::today()->startOfMonth()->toDateString();
            $dateTo   = $request->filled('date_to') ? Carbon::parse($request->date_to)->toDateString() : Carbon::today()->toDateString();

            $query = Attendance::with([
                'user:id,employee_id,name,email,department',
                'marker:id,name',
            ])
            ->whereBetween('date', [$dateFrom, $dateTo]);

            if ($request->filled('user_id') && $request->user_id !== 'all') {
                $query->where('user_id', $request->user_id);
            }

            if ($request->filled('status') && $request->status !== 'all') {
                $query->where('status', $request->status);
            }

            if ($request->filled('department') && $request->department !== 'all') {
                $dept = $request->department;
                $query->whereHas('user', function ($q) use ($dept) {
                    $q->where('department', $dept);
                });
            }

            $perPage = (int) $request->get('per_page', 20);
            $attendances = $query->orderBy('date', 'desc')->paginate($perPage);

            return response()->json([
                'success' => true,
                'data'    => $attendances->items(),
                'meta'    => [
                    'total'        => $attendances->total(),
                    'current_page' => $attendances->currentPage(),
                    'per_page'     => $attendances->perPage(),
                    'last_page'    => $attendances->lastPage(),
                ],
            ]);
        } catch (\Exception $e) {
            return $this->exceptionResponse('HrAttendanceController : report', $e);
        }
    }
}

