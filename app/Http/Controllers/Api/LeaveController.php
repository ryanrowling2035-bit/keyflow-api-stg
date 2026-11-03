<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\LeaveNotificationMail;
use App\Models\Leave;
use App\Models\LeaveBalance;
use App\Models\Notification;
use App\Models\User;
use App\Services\FileStorageService;
use App\Traits\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class LeaveController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ?FileStorageService $fileStorage = null
    ) {}

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
     * Get list of leave types
     */
    public function getLeaveTypes()
    {
        $types = [
            ['value' => 'annual', 'label' => 'Annual Leave', 'default_quota' => 21],
            ['value' => 'sick', 'label' => 'Sick Leave', 'default_quota' => 10],
            ['value' => 'emergency', 'label' => 'Emergency Leave', 'default_quota' => 5],
            ['value' => 'unpaid', 'label' => 'Unpaid Leave', 'default_quota' => 30],
            ['value' => 'wfh', 'label' => 'Work From Home', 'default_quota' => 12],
            ['value' => 'compensatory', 'label' => 'Compensatory Leave', 'default_quota' => 5],
        ];

        $this->logActivity([
            'action' => 'viewed',
            'module' => 'leave',
            'description' => 'Viewed leave types',
        ]);

        return $this->success($types, 'Leave types fetched successfully');
    }

    /**
     * Apply for a leave (Employee)
     */
    public function applyLeave(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'leave_type' => 'required|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'required|string|max:1000',
            'attachment' => 'nullable|file|mimes:jpg,jpeg,png,pdf,doc,docx|max:5120',
            'staff_name' => 'nullable|string|max:255',
            'user_id' => 'nullable',
            'last_working_day' => 'nullable|date',
            'return_date' => 'nullable|date',
            'has_manager_approved' => 'nullable|string',
            'handover_to' => 'nullable|string|max:255',
            'has_doctor_note' => 'nullable|string|max:10',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 422, $validator->errors());
        }

        try {
            $user = auth()->user();
            if (!$user) {
                return $this->error('Unauthenticated', 401);
            }

            $targetUser = $user;
            if ($request->filled('user_id') && is_numeric($request->user_id) && (int) $request->user_id !== $user->id) {
                $foundUser = User::find($request->user_id);
                if ($foundUser) {
                    $targetUser = $foundUser;
                }
            }

            $startDate = Carbon::parse($request->start_date);
            $endDate = Carbon::parse($request->end_date);

            // Calculate working days (excluding weekends Sat & Sun)
            $workingDays = $startDate->diffInDaysFiltered(function (Carbon $date) {
                return !$date->isWeekend();
            }, $endDate->copy()->addDay());

            if ($workingDays <= 0) {
                $workingDays = 1.0;
            }

            $attachmentUrl = null;
            if ($request->hasFile('attachment')) {
                $file = $request->file('attachment');
                if ($this->fileStorage) {
                    $attachmentUrl = $this->fileStorage->uploadPublicFile($file, 'leaves');
                } else {
                    $path = $file->store('leaves', 'public');
                    $attachmentUrl = Storage::disk('public')->url($path);
                }
            }

            $lastWorkingDay = $request->filled('last_working_day')
                ? Carbon::parse($request->last_working_day)->toDateString()
                : $startDate->toDateString();

            $returnDate = $request->filled('return_date')
                ? Carbon::parse($request->return_date)->toDateString()
                : $endDate->toDateString();

            $leave = Leave::create([
                'user_id' => $targetUser->id,
                'staff_name' => $request->input('staff_name', $targetUser->name),
                'leave_type' => $request->leave_type,
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'last_working_day' => $lastWorkingDay,
                'return_date' => $returnDate,
                'working_days' => $workingDays,
                'reason' => $request->reason,
                'has_manager_approved' => $request->input('has_manager_approved', 'Yes'),
                'handover_to' => $request->input('handover_to'),
                'has_doctor_note' => $request->input('has_doctor_note', 'NO'),
                'attachment_url' => $attachmentUrl,
                'status' => 'pending',
            ]);

            // Notify manager or admin users
            $manager = null;
            $managerId = $targetUser->manager?->id ?? $targetUser->manager_id;
            if (!$managerId && $targetUser->createdBy) {
                $managerId = $targetUser->createdBy;
            }
            if ($managerId) {
                $manager = User::find($managerId);
            }

            if ($manager) {
                try {
                    Notification::create([
                        'userId' => $manager->id,
                        'type' => 'leave_requested',
                        'title' => 'New Leave Request',
                        'message' => "{$targetUser->name} has requested {$workingDays} day(s) of " . ucfirst($request->leave_type) . " leave.",
                        'module' => 'leave',
                        'moduleId' => $leave->id,
                        'actorId' => $targetUser->id,
                        'isRead' => false,
                    ]);
                } catch (\Throwable $tn) {}

                // Dispatch Email Notification to Manager
                if (!empty($manager->email)) {
                    try {
                        Mail::to($manager->email)->send(new LeaveNotificationMail('submitted', [
                            'employee_name' => $targetUser->name,
                            'leave_type' => $request->leave_type,
                            'start_date' => $startDate->toDateString(),
                            'end_date' => $endDate->toDateString(),
                            'working_days' => $workingDays,
                            'reason' => $request->reason,
                            'handover_to' => $request->input('handover_to'),
                            'has_manager_approved' => $request->input('has_manager_approved', 'Yes'),
                            'has_doctor_note' => $request->input('has_doctor_note', 'NO'),
                        ]));
                    } catch (\Throwable $tm) {
                        Log::warning('Leave submission email notification failed: ' . $tm->getMessage());
                    }
                }
            }

            $leave->load(['user:id,name,email,department,designation,profilePhoto']);

            $this->logActivity([
                'userId' => auth()->id(),
                'action' => 'created',
                'module' => 'leave',
                'moduleId' => $leave->id,
                'description' => "Applied for " . ucfirst($request->leave_type) . " leave",
            ]);

            return $this->success($leave, 'Leave application submitted successfully', 201);
        } catch (\Throwable $e) {
            return $this->exceptionResponse('LeaveController@applyLeave', $e);
        }
    }

    /**
     * Get all leave requests (Admin/Manager view)
     */
    public function getAll(Request $request)
    {
        try {
            $query = Leave::with([
                'user:id,name,email,department,designation,profilePhoto',
                'approver:id,name',
                'rejecter:id,name',
                'leaveTypeDetails:id,name',
            ]);

            // Filter by status
            if ($request->filled('status') && $request->status !== 'all') {
                $query->where('status', $request->status);
            }

            // Filter by leave_type
            if ($request->filled('leave_type') && $request->leave_type !== 'all') {
                $query->where('leave_type', $request->leave_type);
            }

            // Filter by employee_id / user_id
            if ($request->filled('employee_id')) {
                $query->where('user_id', $request->employee_id);
            }

            // Date filters
            if ($request->filled('start_date')) {
                $query->where('start_date', '>=', $request->start_date);
            }
            if ($request->filled('end_date')) {
                $query->where('end_date', '<=', $request->end_date);
            }

            // Search filter (employee name/email)
            if ($request->filled('search')) {
                $search = $request->search;
                $query->whereHas('user', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            }

            $query->orderBy('created_at', 'desc');

            $perPage = (int) $request->get('per_page', 15);
            $leaves = $query->paginate($perPage);

            // Compute summary statistics
            $stats = [
                'pending' => Leave::where('status', 'pending')->count(),
                'approved' => Leave::where('status', 'approved')->count(),
                'rejected' => Leave::where('status', 'rejected')->count(),
                'onLeaveToday' => Leave::where('status', 'approved')
                    ->whereDate('start_date', '<=', now())
                    ->whereDate('end_date', '>=', now())
                    ->count(),
            ];

            $this->logActivity([
                'userId' => auth()->id(),
                'action' => 'viewed',
                'module' => 'leave',
                'description' => 'Viewed all leave requests',
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Leave requests fetched successfully',
                'data' => $leaves->items(),
                'stats' => $stats,
                'meta' => [
                    'current_page' => $leaves->currentPage(),
                    'from' => $leaves->firstItem(),
                    'last_page' => $leaves->lastPage(),
                    'per_page' => $leaves->perPage(),
                    'to' => $leaves->lastItem(),
                    'total' => $leaves->total(),
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->exceptionResponse('LeaveController@getAll', $e);
        }
    }

    /**
     * Get current user's own leave requests
     */
    public function getMyLeaves(Request $request)
    {
        try {
            $userId = auth()->id();
            if (!$userId) {
                return $this->error('Unauthenticated', 401);
            }

            $query = Leave::where('user_id', $userId)
                ->with(['approver:id,name', 'rejecter:id,name', 'leaveTypeDetails:id,name']);

            if ($request->filled('status') && $request->status !== 'all') {
                $query->where('status', $request->status);
            }

            if ($request->filled('leave_type') && $request->leave_type !== 'all') {
                $query->where('leave_type', $request->leave_type);
            }

            $query->orderBy('created_at', 'desc');

            $perPage = (int) $request->get('per_page', 15);
            $leaves = $query->paginate($perPage);

            $this->logActivity([
                'userId' => auth()->id(),
                'action' => 'viewed',
                'module' => 'leave',
                'description' => 'Viewed own leave requests',
            ]);

            return $this->success($leaves, 'Your leave requests fetched successfully');
        } catch (\Throwable $e) {
            return $this->exceptionResponse('LeaveController@getMyLeaves', $e);
        }
    }

    /**
     * Get single leave request details by ID
     */
    public function getLeaveById($id)
    {
        try {
            $leave = Leave::with([
                'user:id,name,email,department,designation,profilePhoto,joining_date',
                'approver:id,name',
                'rejecter:id,name',
                'leaveTypeDetails:id,name',
            ])->find($id);

            if (!$leave) {
                return $this->error('Leave request not found', 444);
            }

            // Include user leave balance summary
            $balance = LeaveBalance::getOrCreateForUser($leave->user_id);
            $leaveData = $leave->toArray();
            $leaveData['user_balance'] = [
                'annual_remaining' => max(0, $balance->annual_quota - $balance->annual_used),
                'sick_remaining' => max(0, $balance->sick_quota - $balance->sick_used),
                'emergency_remaining' => max(0, $balance->emergency_quota - $balance->emergency_used),
                'wfh_remaining' => max(0, $balance->wfh_quota - $balance->wfh_used),
            ];

            $this->logActivity([
                'userId' => auth()->id(),
                'action' => 'viewed',
                'module' => 'leave',
                'moduleId' => $leave->id,
                'description' => "Viewed leave request details",
            ]);

            return $this->success($leaveData, 'Leave request details fetched successfully');
        } catch (\Throwable $e) {
            return $this->exceptionResponse('LeaveController@getLeaveById', $e);
        }
    }

    /**
     * Approve leave request
     */
    public function approveLeave($id)
    {
        try {
            $leave = Leave::find($id);

            if (!$leave) {
                return $this->error('Leave request not found', 404);
            }

            $approver = auth()->user();

            $leave->update([
                'status' => 'approved',
                'approved_by' => $approver?->id,
                'approved_at' => now(),
            ]);

            // Deduct from employee leave balance
            try {
                $balance = LeaveBalance::getOrCreateForUser($leave->user_id);
                $balance->deductLeave($leave->leave_type, $leave->working_days ?? 1);
            } catch (\Throwable $tb) {
                // Ignore balance exception
            }

            // Notify employee
            try {
                Notification::create([
                    'userId' => $leave->user_id,
                    'type' => 'leave_approved',
                    'title' => 'Leave Approved',
                    'message' => "Your " . ucfirst($leave->leave_type) . " leave request from {$leave->start_date} to {$leave->end_date} has been approved.",
                    'module' => 'leave',
                    'moduleId' => $leave->id,
                    'actorId' => $approver?->id,
                    'isRead' => false,
                ]);
            } catch (\Throwable $tn) {
                // Ignore notification exception
            }

            $leave->load(['user:id,name,email,department,designation,profilePhoto', 'approver:id,name']);

            // Dispatch Email Notification to Employee
            if ($leave->user && !empty($leave->user->email)) {
                try {
                    Mail::to($leave->user->email)->send(new LeaveNotificationMail('approved', [
                        'employee_name' => $leave->user->name,
                        'leave_type' => $leave->leave_type,
                        'start_date' => $leave->start_date instanceof Carbon ? $leave->start_date->toDateString() : (string) $leave->start_date,
                        'end_date' => $leave->end_date instanceof Carbon ? $leave->end_date->toDateString() : (string) $leave->end_date,
                        'working_days' => $leave->working_days ?? 1,
                        'reason' => $leave->reason,
                        'handover_to' => $leave->handover_to,
                        'actioned_by' => $approver?->name ?? 'HR Manager',
                    ]));
                } catch (\Throwable $tm) {
                    Log::warning('Leave approval email notification failed: ' . $tm->getMessage());
                }
            }

            $this->logActivity([
                'userId' => auth()->id(),
                'action' => 'updated',
                'module' => 'leave',
                'moduleId' => $leave->id,
                'description' => "Approved leave request",
            ]);

            return $this->success($leave, 'Leave request approved successfully');
        } catch (\Throwable $e) {
            return $this->exceptionResponse('LeaveController@approveLeave', $e);
        }
    }

    /**
     * Reject leave request
     */
    public function rejectLeave(Request $request, $id)
    {
        try {
            $leave = Leave::find($id);

            if (!$leave) {
                return $this->error('Leave request not found', 404);
            }

            $reason = $request->input('reason') ?? $request->input('rejection_reason') ?? 'Request rejected by HR/Manager';
            $rejecter = auth()->user();

            $leave->update([
                'status' => 'rejected',
                'rejection_reason' => $reason,
                'rejected_by' => $rejecter?->id,
                'rejected_at' => now(),
            ]);

            // Notify employee
            try {
                Notification::create([
                    'userId' => $leave->user_id,
                    'type' => 'leave_rejected',
                    'title' => 'Leave Rejected',
                    'message' => "Your " . ucfirst($leave->leave_type) . " leave request was rejected. Reason: {$reason}",
                    'module' => 'leave',
                    'moduleId' => $leave->id,
                    'actorId' => $rejecter?->id,
                    'isRead' => false,
                ]);
            } catch (\Throwable $tn) {
                // Ignore notification exception
            }

            $leave->load(['user:id,name,email,department,designation,profilePhoto', 'rejecter:id,name']);

            // Dispatch Email Notification to Employee
            if ($leave->user && !empty($leave->user->email)) {
                try {
                    Mail::to($leave->user->email)->send(new LeaveNotificationMail('rejected', [
                        'employee_name' => $leave->user->name,
                        'leave_type' => $leave->leave_type,
                        'start_date' => $leave->start_date instanceof Carbon ? $leave->start_date->toDateString() : (string) $leave->start_date,
                        'end_date' => $leave->end_date instanceof Carbon ? $leave->end_date->toDateString() : (string) $leave->end_date,
                        'working_days' => $leave->working_days ?? 1,
                        'reason' => $leave->reason,
                        'rejection_reason' => $reason,
                        'actioned_by' => $rejecter?->name ?? 'HR Manager',
                    ]));
                } catch (\Throwable $tm) {
                    Log::warning('Leave rejection email notification failed: ' . $tm->getMessage());
                }
            }

            $this->logActivity([
                'userId' => auth()->id(),
                'action' => 'updated',
                'module' => 'leave',
                'moduleId' => $leave->id,
                'description' => "Rejected leave request",
            ]);

            return $this->success($leave, 'Leave request rejected successfully');
        } catch (\Throwable $e) {
            return $this->exceptionResponse('LeaveController@rejectLeave', $e);
        }
    }

    /**
     * Cancel leave request (Employee)
     */
    public function cancelLeave($id)
    {
        try {
            $leave = Leave::find($id);

            if (!$leave) {
                return $this->error('Leave request not found', 444);
            }

            if ($leave->user_id !== auth()->id()) {
                return $this->error('Unauthorized to cancel this leave request', 403);
            }

            if ($leave->status !== 'pending') {
                return $this->error("Only pending leave requests can be cancelled", 422);
            }

            $leave->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
            ]);

            $this->logActivity([
                'userId' => auth()->id(),
                'action' => 'updated',
                'module' => 'leave',
                'moduleId' => $leave->id,
                'description' => "Cancelled leave request",
            ]);

            return $this->success($leave, 'Leave request cancelled successfully');
        } catch (\Throwable $e) {
            return $this->exceptionResponse('LeaveController@cancelLeave', $e);
        }
    }

    /**
     * Get leave balance for a specific user
     */
    public function getLeaveBalance($userId = null)
    {
        try {
            $targetUserId = $userId ?? auth()->id();
            if (!$targetUserId) {
                return $this->error('User ID required', 400);
            }

            $user = User::select('id', 'name', 'email', 'department', 'designation')->find($targetUserId);
            if (!$user) {
                return $this->error('User not found', 444);
            }

            $balance = LeaveBalance::getOrCreateForUser($targetUserId);

            $data = [
                'user' => $user,
                'year' => $balance->year,
                'balances' => [
                    'annual' => [
                        'quota' => $balance->annual_quota,
                        'used' => $balance->annual_used,
                        'remaining' => max(0, $balance->annual_quota - $balance->annual_used),
                    ],
                    'sick' => [
                        'quota' => $balance->sick_quota,
                        'used' => $balance->sick_used,
                        'remaining' => max(0, $balance->sick_quota - $balance->sick_used),
                    ],
                    'emergency' => [
                        'quota' => $balance->emergency_quota,
                        'used' => $balance->emergency_used,
                        'remaining' => max(0, $balance->emergency_quota - $balance->emergency_used),
                    ],
                    'unpaid' => [
                        'quota' => $balance->unpaid_quota,
                        'used' => $balance->unpaid_used,
                        'remaining' => max(0, $balance->unpaid_quota - $balance->unpaid_used),
                    ],
                    'wfh' => [
                        'quota' => $balance->wfh_quota,
                        'used' => $balance->wfh_used,
                        'remaining' => max(0, $balance->wfh_quota - $balance->wfh_used),
                    ],
                    'compensatory' => [
                        'quota' => $balance->compensatory_quota,
                        'used' => $balance->compensatory_used,
                        'remaining' => max(0, $balance->compensatory_quota - $balance->compensatory_used),
                    ],
                ],
            ];

            $this->logActivity([
                'userId' => auth()->id(),
                'action' => 'viewed',
                'module' => 'leave',
                'description' => "Viewed leave balance for user {$user->name}",
            ]);

            return $this->success($data, 'Leave balance fetched successfully');
        } catch (\Throwable $e) {
            return $this->exceptionResponse('LeaveController@getLeaveBalance', $e);
        }
    }
}
