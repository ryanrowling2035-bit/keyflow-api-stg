<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\User;
use App\Traits\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class AttendanceController extends Controller
{
    use ApiResponse;

    protected function logActivity(array $data): void
    {
        if (auth()->id()) {
            $data['userId'] = auth()->id();
            try {
                logUserActivity($data);
            } catch (\Throwable $e) {}
        }
    }

    /**
     * GET /api/hr/attendance/today-status
     * Fetch punch status for an employee for today
     */
    public function getTodayStatus(Request $request)
    {
        try {
            $user = Auth::user();
            $rawUserId = $request->query('user_id');

            if (!$rawUserId || $rawUserId === 'current' || !is_numeric($rawUserId)) {
                $targetUserId = $user ? $user->id : 101;
            } else {
                $targetUserId = (int) $rawUserId;
            }

            $todayStr = Carbon::today()->toDateString();

            $record = Attendance::where('user_id', $targetUserId)
                ->where('date', $todayStr)
                ->first();

            if (!$record && (!$rawUserId || $rawUserId === 'current')) {
                $record = Attendance::where('date', $todayStr)->first();
            }

            if ($record) {
                return response()->json([
                    'success' => true,
                    'data' => $record,
                    'isCheckedIn' => !empty($record->check_in) && $record->check_in !== '--',
                    'isCheckedOut' => !empty($record->check_out) && $record->check_out !== '--',
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => null,
                'isCheckedIn' => false,
                'isCheckedOut' => false,
            ]);
        } catch (\Throwable $e) {
            return $this->exceptionResponse('AttendanceController@getTodayStatus', $e);
        }
    }

    /**
     * POST /api/hr/attendance/check-in
     * Employee punch check-in
     */
    public function checkIn(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'work_location' => 'nullable|string|in:Office,Remote,Field',
                'notes' => 'nullable|string|max:500',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = Auth::user();
            $rawUserId = $request->input('user_id');
            if (!$rawUserId || $rawUserId === 'current' || !is_numeric($rawUserId)) {
                $userId = $user ? $user->id : 101;
            } else {
                $userId = (int) $rawUserId;
            }
            $targetUser = ($user && $user->id === $userId) ? $user : User::find($userId);
            $userName = $request->input('employee_name');
            if (!$userName || $userName === 'Logged In Employee' || $userName === 'Logged In User') {
                $userName = $targetUser ? ($targetUser->name ?? $targetUser->first_name) : 'Employee';
            }
            $workLocation = $request->input('work_location', 'Office');
            $notes = $request->input('notes', '');

            $todayStr = Carbon::today()->toDateString();
            $nowTimeStr = Carbon::now()->format('h:i A');

            $existing = Attendance::where('user_id', $userId)
                ->where('date', $todayStr)
                ->first();

            $nowHour = (int) Carbon::now()->format('H');
            $nowMinute = (int) Carbon::now()->format('i');
            $isLate = ($nowHour > 9) || ($nowHour === 9 && $nowMinute > 15);

            $status = $isLate ? 'late' : 'present';

            if ($existing) {
                $existing->update([
                    'check_in' => $nowTimeStr,
                    'work_location' => $workLocation,
                    'status' => $existing->status === 'on_leave' ? 'on_leave' : $status,
                    'working_hours' => 'Currently Active',
                    'notes' => $notes ?: ($isLate ? 'Late Check-in' : 'Standard Check-in'),
                    'updated_by' => 'Employee Self'
                ]);
                $record = $existing->fresh();
            } else {
                $record = Attendance::create([
                    'user_id' => $userId,
                    'employee_name' => $userName,
                    'employee_code' => 'EMP-' . $userId,
                    'designation' => $user ? ($user->designation ?? 'Team Member') : 'Team Member',
                    'department' => $user ? ($user->department ?? 'Sales') : 'Sales',
                    'profile_photo' => $user ? ($user->profile_photo ?? $user->profilePhoto ?? '') : '',
                    'date' => $todayStr,
                    'status' => $status,
                    'check_in' => $nowTimeStr,
                    'check_out' => '',
                    'work_location' => $workLocation,
                    'working_hours' => 'Currently Active',
                    'notes' => $notes ?: ($isLate ? 'Late Check-in' : 'Standard Check-in'),
                    'updated_by' => 'Employee Self'
                ]);
            }

            $this->logActivity([
                'activity' => 'Checked in successfully at ' . $nowTimeStr,
                'type' => 'Attendance'
            ]);

            return response()->json([
                'success' => true,
                'message' => "Checked in successfully at {$nowTimeStr}",
                'data' => $record
            ]);
        } catch (\Throwable $e) {
            return $this->exceptionResponse('AttendanceController@checkIn', $e);
        }
    }

    /**
     * POST /api/hr/attendance/check-out
     * Employee punch check-out
     */
    public function checkOut(Request $request)
    {
        try {
            $user = Auth::user();
            $rawUserId = $request->input('user_id');
            if (!$rawUserId || $rawUserId === 'current' || !is_numeric($rawUserId)) {
                $userId = $user ? $user->id : 101;
            } else {
                $userId = (int) $rawUserId;
            }
            $notes = $request->input('notes', '');

            $todayStr = Carbon::today()->toDateString();
            $nowTimeStr = Carbon::now()->format('h:i A');

            $existing = Attendance::where('user_id', $userId)
                ->where('date', $todayStr)
                ->first();

            if (!$existing) {
                // Try finding any active record for today
                $existing = Attendance::where('date', $todayStr)->first();
            }

            if ($existing) {
                $updatedNotes = $existing->notes;
                if ($notes) {
                    $updatedNotes = $existing->notes ? "{$existing->notes} | Out: {$notes}" : "Out: {$notes}";
                }

                $existing->update([
                    'check_out' => $nowTimeStr,
                    'working_hours' => '8h 00m',
                    'notes' => $updatedNotes,
                ]);

                $this->logActivity([
                    'activity' => 'Checked out successfully at ' . $nowTimeStr,
                    'type' => 'Attendance'
                ]);

                return response()->json([
                    'success' => true,
                    'message' => "Checked out successfully at {$nowTimeStr}",
                    'data' => $existing->fresh()
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'No active check-in found for today.'
            ], 404);
        } catch (\Throwable $e) {
            return $this->exceptionResponse('AttendanceController@checkOut', $e);
        }
    }

    /**
     * GET /api/hr/attendance/daily
     * Fetch daily roster listing with filters and stats
     */
    public function getDailyAttendance(Request $request)
    {
        try {
            $date = $request->query('date', Carbon::today()->toDateString());
            $search = $request->query('search', '');
            $department = $request->query('department', 'all');
            $status = $request->query('status', 'all');

            $query = Attendance::whereDate('date', $date);

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('employee_name', 'like', "%{$search}%")
                      ->orWhere('employee_code', 'like', "%{$search}%")
                      ->orWhere('department', 'like', "%{$search}%");
                });
            }

            if ($department && $department !== 'all') {
                $query->where('department', $department);
            }

            if ($status && $status !== 'all') {
                $query->where('status', $status);
            }

            $records = $query->orderBy('created_at', 'desc')->get();

            // Calculate statistics
            $allTodayRecords = Attendance::whereDate('date', $date)->get();
            $stats = [
                'total' => $allTodayRecords->count(),
                'present' => $allTodayRecords->where('status', 'present')->count(),
                'late' => $allTodayRecords->where('status', 'late')->count(),
                'half_day' => $allTodayRecords->where('status', 'half_day')->count(),
                'on_leave' => $allTodayRecords->where('status', 'on_leave')->count(),
                'absent' => $allTodayRecords->where('status', 'absent')->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => $records,
                'stats' => $stats
            ]);
        } catch (\Throwable $e) {
            return $this->exceptionResponse('AttendanceController@getDailyAttendance', $e);
        }
    }

    /**
     * POST /api/hr/attendance/manual-mark
     * HR Manual Attendance Marking or Override
     */
    public function markManualAttendance(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'employee_name' => 'required|string',
                'status' => 'required|string|in:present,late,half_day,absent,on_leave',
                'date' => 'required|date',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $id = $request->input('id');
            $userId = $request->input('user_id', 999);
            $employeeName = $request->input('employee_name');
            $department = $request->input('department', 'Sales');
            $date = $request->input('date', Carbon::today()->toDateString());
            $status = $request->input('status', 'present');
            $checkIn = $request->input('check_in', '09:00 AM');
            $checkOut = $request->input('check_out', '06:00 PM');
            $workLocation = $request->input('work_location', 'Office');
            $notes = $request->input('notes', 'HR Manual Mark');

            $workingHours = '0h 0m';
            if ($status === 'present' || $status === 'late') {
                $workingHours = '8h 00m';
            } else if ($status === 'half_day') {
                $workingHours = '4h 00m';
            }

            $user = Auth::user();
            $updatedBy = $user ? ($user->name ?? 'HR Admin') : 'HR Admin';

            if ($id) {
                $record = Attendance::find($id);
            } else {
                $record = Attendance::where('user_id', $userId)->where('date', $date)->first();
            }

            $payload = [
                'user_id' => $userId,
                'employee_name' => $employeeName,
                'employee_code' => 'EMP-' . $userId,
                'designation' => 'Team Member',
                'department' => $department,
                'date' => $date,
                'status' => $status,
                'check_in' => $checkIn,
                'check_out' => $checkOut,
                'work_location' => $workLocation,
                'working_hours' => $workingHours,
                'notes' => $notes,
                'updated_by' => $updatedBy,
            ];

            if ($record) {
                $record->update($payload);
                $record = $record->fresh();
            } else {
                $record = Attendance::create($payload);
            }

            $this->logActivity([
                'activity' => 'Manual Attendance marked for ' . $employeeName,
                'type' => 'Attendance'
            ]);

            return response()->json([
                'success' => true,
                'message' => "Attendance for {$employeeName} on {$date} saved successfully.",
                'data' => $record
            ]);
        } catch (\Throwable $e) {
            return $this->exceptionResponse('AttendanceController@markManualAttendance', $e);
        }
    }

    /**
     * GET /api/hr/attendance/monthly-report
     * Get monthly matrix data report
     */
    public function getMonthlyReport(Request $request)
    {
        try {
            $month = (int) $request->query('month', Carbon::now()->month);
            $year = (int) $request->query('year', Carbon::now()->year);

            $records = Attendance::whereYear('date', $year)
                ->whereMonth('date', $month)
                ->get();

            return response()->json([
                'success' => true,
                'month' => $month,
                'year' => $year,
                'data' => $records
            ]);
        } catch (\Throwable $e) {
            return $this->exceptionResponse('AttendanceController@getMonthlyReport', $e);
        }
    }
}
