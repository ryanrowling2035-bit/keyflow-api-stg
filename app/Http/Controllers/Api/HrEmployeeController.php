<?php

namespace App\Http\Controllers\Api;

use App\Constants\Constants;
use App\Http\Controllers\Controller;
use App\Models\EmployeeDocument;
use App\Models\Leave;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Models\UserMeta;
use App\Services\FileStorageService;
use App\Services\UserService;
use App\Traits\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class HrEmployeeController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected FileStorageService $fileStorage,
        protected UserService $userService
    ) {}

    /**
     * Employee Directory / Listing
     * Filters: department, team, status, manager_id, joining_date, search
     */
    public function index(Request $request)
    {
        try {
            $query = User::with([
                'role:id,name',
                'teams:teams.id,teams.name',
                'meta',
            ]);

            // Search by Name, Email, Employee ID, Phone
            if ($request->filled('search')) {
                $search = strtolower(trim($request->search));
                $query->where(function ($q) use ($search) {
                    $q->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"])
                      ->orWhereRaw('LOWER(email) LIKE ?', ["%{$search}%"])
                      ->orWhereRaw('LOWER(employee_id) LIKE ?', ["%{$search}%"])
                      ->orWhereRaw('LOWER(phone) LIKE ?', ["%{$search}%"]);
                });
            }

            // Department filter
            if ($request->filled('department') && $request->department !== 'all') {
                $depts = is_array($request->department) ? $request->department : explode(',', $request->department);
                $query->whereIn('department', $depts);
            }

            // Team filter
            $teamParam = $request->team_id ?? $request->team;
            if (!empty($teamParam) && $teamParam !== 'all') {
                $teamIds = is_array($teamParam) ? $teamParam : explode(',', $teamParam);
                $query->whereHas('teams', function ($q) use ($teamIds) {
                    $q->whereIn('teams.id', $teamIds)->orWhereIn('teams.name', $teamIds);
                });
            }

            // Status filter (active/inactive)
            if ($request->filled('status') && $request->status !== 'all') {
                $statuses = is_array($request->status) ? $request->status : explode(',', $request->status);
                $query->whereIn('status', $statuses);
            }

            // Manager filter (checks user_meta or assigned teams)
            $managerParam = $request->manager_id ?? $request->manager;
            if (!empty($managerParam) && $managerParam !== 'all') {
                $managerIds = is_array($managerParam) ? $managerParam : explode(',', $managerParam);
                $query->where(function ($q) use ($managerIds) {
                    $q->whereHas('meta', fn ($m) => $m->where('metaKey', '_managerId')->whereIn('metaValue', $managerIds))
                      ->orWhereHas('teams', function ($t) use ($managerIds) {
                          $t->whereHas('users', fn ($u) => $u->whereIn('users.id', $managerIds));
                      });
                });
            }

            // Joining Date range filter
            if ($request->filled('joining_date_from')) {
                $query->whereDate('joining_date', '>=', $request->joining_date_from);
            }
            if ($request->filled('joining_date_to')) {
                $query->whereDate('joining_date', '<=', $request->joining_date_to);
            }

            $perPage = (int) $request->get('per_page', 15);
            $employees = $query->orderBy('created_at', 'desc')->paginate($perPage);

            // Append today's attendance status for each employee
            $today = Carbon::today()->toDateString();
            $employeeIds = $employees->pluck('id')->toArray();

            $todayAttendances = DB::table('attendances')
                ->whereIn('user_id', $employeeIds)
                ->where('date', $today)
                ->pluck('status', 'user_id');

            // Pre-fetch team managers to resolve managers for users whose manager_id is null
            $teamManagers = DB::table('assign_team_members')
                ->join('users', 'assign_team_members.userId', '=', 'users.id')
                ->join('roles', 'users.roleId', '=', 'roles.id')
                ->where('roles.name', 'Manager')
                ->whereNull('users.deleted_at')
                ->select('assign_team_members.teamId', 'users.id as manager_id', 'users.name as manager_name')
                ->get()
                ->groupBy('teamId');

            $data = $employees->getCollection()->map(function ($emp) use ($todayAttendances, $teamManagers) {
                // Determine manager
                $manager = null;
                if ($emp->manager) {
                    $manager = ['id' => $emp->manager->id, 'name' => $emp->manager->name];
                } elseif ($emp->teams->isNotEmpty()) {
                    foreach ($emp->teams as $t) {
                        $m = $teamManagers->get($t->id)?->first();
                        if ($m && $m->manager_id != $emp->id) {
                            $manager = ['id' => $m->manager_id, 'name' => $m->manager_name];
                            break;
                        }
                    }
                }

                // Determine department fallback based on role if null
                $dept = $emp->department;
                if (empty($dept)) {
                    $roleName = strtolower($emp->role?->name ?? '');
                    if (str_contains($roleName, 'agent')) {
                        $dept = 'Sales';
                    } elseif (str_contains($roleName, 'manager')) {
                        $dept = 'Sales';
                    } elseif (str_contains($roleName, 'hr')) {
                        $dept = 'Human Resources';
                    } elseif (str_contains($roleName, 'admin')) {
                        $dept = 'Management';
                    }
                }

                $teamsList = $emp->teams->map(function ($t) {
                    return ['id' => $t->id, 'name' => $t->name];
                });
                $firstTeam = $emp->teams->first();

                return [
                    'id'                       => $emp->id,
                    'employee_id'              => $emp->employee_id ?? $emp->agentCode ?? ('EMP-' . str_pad($emp->id, 4, '0', STR_PAD_LEFT)),
                    'name'                     => $emp->name,
                    'email'                    => $emp->email,
                    'phone'                    => $emp->phone,
                    'department'               => $dept,
                    'department_name'          => $dept,
                    'team'                     => $firstTeam ? ['id' => $firstTeam->id, 'name' => $firstTeam->name] : null,
                    'team_id'                  => $firstTeam?->id,
                    'team_name'                => $firstTeam?->name,
                    'teams'                    => $teamsList,
                    'team_names'               => $emp->teams->pluck('name')->toArray(),
                    'role'                     => $emp->role ? ['id' => $emp->role->id, 'name' => $emp->role->name] : null,
                    'roleData'                 => $emp->role ? ['id' => $emp->role->id, 'name' => $emp->role->name] : null,
                    'roleName'                 => $emp->role?->name,
                    'designation'              => $emp->designation ?? $emp->role?->name,
                    'status'                   => $emp->status ?? 'active',
                    'employment_status'        => $emp->status ?? 'active',
                    'joining_date'             => $emp->joining_date ? Carbon::parse($emp->joining_date)->toDateString() : ($emp->created_at ? Carbon::parse($emp->created_at)->toDateString() : null),
                    'manager'                  => $manager,
                    'manager_id'               => $manager['id'] ?? null,
                    'manager_name'             => $manager['name'] ?? null,
                    'leave_balance'            => (float) ($emp->meta?->firstWhere('metaKey', '_leaveBalance')?->metaValue ?? 30.00),
                    'annual_leave_allowed'     => (int) ($emp->meta?->firstWhere('metaKey', '_annualLeaveAllowed')?->metaValue ?? 30),
                    'today_attendance_status'  => $todayAttendances[$emp->id] ?? 'not_marked',
                    'profile_photo'            => $emp->profilePhoto,
                    'created_at'               => $emp->created_at?->toIso8601String(),
                ];
            });

            return response()->json([
                'success' => true,
                'data'    => $data,
                'meta'    => [
                    'total'        => $employees->total(),
                    'current_page' => $employees->currentPage(),
                    'per_page'     => $employees->perPage(),
                    'last_page'    => $employees->lastPage(),
                ],
            ]);
        } catch (\Exception $e) {
            return $this->exceptionResponse('HrEmployeeController : index', $e);
        }
    }

    /**
     * Store new employee manually
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'                     => 'required|string|max:255',
            'email'                    => 'required|email|unique:users,email',
            'personal_email'           => 'nullable|email',
            'phone'                    => 'nullable|string|max:30',
            'role_id'                  => 'nullable|exists:roles,id',
            'employee_id'              => 'nullable|string|max:50|unique:users,employee_id',
            'designation'              => 'nullable|string|max:100',
            'department'               => 'nullable|string|max:100',
            'employment_type'          => 'nullable|string|max:50',
            'manager_id'               => 'nullable|exists:users,id',
            'joining_date'             => 'nullable|date',
            'date_of_birth'            => 'nullable|date',
            'nationality'              => 'nullable|string|max:100',
            'emergency_contact_number' => 'nullable|string|max:50',
            'visa_expiry_date'         => 'nullable|date',
            'passport_expiry_date'     => 'nullable|date',
            'emirates_id_expiry_date'  => 'nullable|date',
            'labour_card_expiry_date'  => 'nullable|date',
            'insurance_expiry_date'    => 'nullable|date',
            'annual_leave_allowed'     => 'nullable|integer|min:0',
            'leave_balance'            => 'nullable|numeric|min:0',
            'team_ids'                 => 'nullable|array',
            'team_ids.*'               => 'exists:teams,id',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 422, $validator->errors());
        }

        try {
            DB::beginTransaction();

            $data = $validator->validated();

            // Auto-generate employee_id if not supplied
            if (empty($data['employee_id'])) {
                $data['employee_id'] = \App\Services\UserService::generateEmployeeId();
            }

            // Default password if not provided
            $data['password'] = Hash::make(Str::random(12));
            $data['status'] = $data['status'] ?? 'active';
            $data['createdBy'] = auth('api')->id();

            if (empty($data['roleId']) && !empty($data['role_id'])) {
                $data['roleId'] = $data['role_id'];
            }

            $user = User::create($data);

            // Save HR extended fields to UserMeta
            $metaMap = [
                'nationality'              => '_nationality',
                'personal_email'           => '_personalEmail',
                'personalEmail'            => '_personalEmail',
                'employment_type'          => '_employmentType',
                'employmentType'           => '_employmentType',
                'manager_id'               => '_managerId',
                'managerId'                => '_managerId',
                'visa_expiry_date'         => '_visaExpiryDate',
                'visaExpiryDate'           => '_visaExpiryDate',
                'passport_expiry_date'     => '_passportExpiryDate',
                'passportExpiryDate'       => '_passportExpiryDate',
                'emirates_id_expiry_date'  => '_emiratesIdExpiryDate',
                'emiratesIdExpiryDate'     => '_emiratesIdExpiryDate',
                'labour_card_expiry_date'  => '_labourContractExpiryDate',
                'labourContractExpiryDate' => '_labourContractExpiryDate',
                'insurance_expiry_date'    => '_insuranceExpiryDate',
                'insuranceExpiryDate'      => '_insuranceExpiryDate',
                'annual_leave_allowed'     => '_annualLeaveAllowed',
                'annualLeaveAllowed'       => '_annualLeaveAllowed',
                'leave_balance'            => '_leaveBalance',
                'leaveBalance'             => '_leaveBalance',
            ];

            foreach ($metaMap as $inputKey => $metaKey) {
                if ($request->filled($inputKey)) {
                    UserMeta::updateOrCreate(
                        ['userId' => $user->id, 'metaKey' => $metaKey],
                        ['metaValue' => $request->input($inputKey)]
                    );
                }
            }

            // Assign teams if provided
            if (!empty($data['team_ids'])) {
                $user->teams()->sync($data['team_ids']);
            }

            // Sync compliance documents & expiry dates to employee_documents
            $this->userService->syncEmployeeDocuments($user->id, $request);

            DB::commit();

            return $this->success($user->load(['role', 'teams', 'meta', 'employeeDocuments']), 'Employee created successfully.', 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->exceptionResponse('HrEmployeeController : store', $e);
        }
    }

    /**
     * Detailed Employee Profile
     */
    public function show($id)
    {
        try {
            $user = User::with([
                'role',
                'teams:teams.id,teams.name',
                'meta',
                'employeeDocuments',
                'leaves' => function ($q) {
                    $q->orderBy('created_at', 'desc')->limit(10);
                },
                'leaveRequests' => function ($q) {
                    $q->orderBy('created_at', 'desc')->limit(10);
                },
                'attendances' => function ($q) {
                    $q->orderBy('date', 'desc')->limit(30);
                },
            ])->find($id);

            if (!$user) {
                return $this->error('Employee not found.', 404);
            }

            $user->append(['manager', 'manager_name', 'manager_id']);

            return $this->success($user, 'Employee profile retrieved successfully.');
        } catch (\Exception $e) {
            return $this->exceptionResponse('HrEmployeeController : show', $e);
        }
    }

    /**
     * Update Employee Profile & HR Details
     */
    public function update(Request $request, $id)
    {
        $user = User::find($id);
        if (!$user) {
            return $this->error('Employee not found.', 404);
        }

        $validator = Validator::make($request->all(), [
            'name'                     => 'sometimes|required|string|max:255',
            'email'                    => 'sometimes|required|email|unique:users,email,' . $id,
            'personal_email'           => 'nullable|email',
            'phone'                    => 'nullable|string|max:30',
            'role_id'                  => 'nullable|exists:roles,id',
            'employee_id'              => 'nullable|string|max:50|unique:users,employee_id,' . $id,
            'designation'              => 'nullable|string|max:100',
            'department'               => 'nullable|string|max:100',
            'employment_type'          => 'nullable|string|max:50',
            'manager_id'               => 'nullable|exists:users,id',
            'joining_date'             => 'nullable|date',
            'date_of_birth'            => 'nullable|date',
            'nationality'              => 'nullable|string|max:100',
            'emergency_contact_number' => 'nullable|string|max:50',
            'visa_expiry_date'         => 'nullable|date',
            'passport_expiry_date'     => 'nullable|date',
            'emirates_id_expiry_date'  => 'nullable|date',
            'labour_card_expiry_date'  => 'nullable|date',
            'insurance_expiry_date'    => 'nullable|date',
            'annual_leave_allowed'     => 'nullable|integer|min:0',
            'leave_balance'            => 'nullable|numeric|min:0',
            'team_ids'                 => 'nullable|array',
            'team_ids.*'               => 'exists:teams,id',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 422, $validator->errors());
        }

        try {
            DB::beginTransaction();

            $data = $validator->validated();
            if (isset($data['role_id'])) {
                $data['roleId'] = $data['role_id'];
            }

            $user->update($data);

            // Update HR extended fields in UserMeta
            $metaMap = [
                'nationality'              => '_nationality',
                'personal_email'           => '_personalEmail',
                'personalEmail'            => '_personalEmail',
                'employment_type'          => '_employmentType',
                'employmentType'           => '_employmentType',
                'manager_id'               => '_managerId',
                'managerId'                => '_managerId',
                'visa_expiry_date'         => '_visaExpiryDate',
                'visaExpiryDate'           => '_visaExpiryDate',
                'passport_expiry_date'     => '_passportExpiryDate',
                'passportExpiryDate'       => '_passportExpiryDate',
                'emirates_id_expiry_date'  => '_emiratesIdExpiryDate',
                'emiratesIdExpiryDate'     => '_emiratesIdExpiryDate',
                'labour_card_expiry_date'  => '_labourContractExpiryDate',
                'labourContractExpiryDate' => '_labourContractExpiryDate',
                'insurance_expiry_date'    => '_insuranceExpiryDate',
                'insuranceExpiryDate'      => '_insuranceExpiryDate',
                'annual_leave_allowed'     => '_annualLeaveAllowed',
                'annualLeaveAllowed'       => '_annualLeaveAllowed',
                'leave_balance'            => '_leaveBalance',
                'leaveBalance'             => '_leaveBalance',
            ];

            foreach ($metaMap as $inputKey => $metaKey) {
                if ($request->has($inputKey)) {
                    UserMeta::updateOrCreate(
                        ['userId' => $user->id, 'metaKey' => $metaKey],
                        ['metaValue' => $request->input($inputKey)]
                    );
                }
            }

            if (isset($data['team_ids'])) {
                $user->teams()->sync($data['team_ids']);
            }

            // Sync compliance documents & expiry dates to employee_documents
            $this->userService->syncEmployeeDocuments($user->id, $request);

            DB::commit();

            return $this->success($user->load(['role', 'teams', 'meta', 'employeeDocuments']), 'Employee profile updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->exceptionResponse('HrEmployeeController : update', $e);
        }
    }

    /**
     * Soft delete employee
     */
    public function destroy($id)
    {
        $user = User::find($id);
        if (!$user) {
            return $this->error('Employee not found.', 404);
        }

        try {
            $user->delete();
            return $this->success([], 'Employee deleted successfully.');
        } catch (\Exception $e) {
            return $this->exceptionResponse('HrEmployeeController : destroy', $e);
        }
    }

    /**
     * Toggle Active / Inactive status
     */
    public function updateStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 422, $validator->errors());
        }

        $rawStatus = strtolower(trim($request->status));
        if (!in_array($rawStatus, ['active', 'inactive', '1', '0', 'true', 'false'])) {
            return $this->error('Status must be active or inactive.', 422);
        }

        $user = User::with('role')->find($id);
        if (!$user) {
            return $this->error('Employee not found.', 404);
        }

        if (auth('api')->id() && auth('api')->id() === (int) $id) {
            return $this->error('You cannot change your own account status.', 403);
        }

        $isDefaultAdmin = (int) $id === 1 && ($user->role?->name === 'SuperAdmin' || $user->roleId == 1);
        $isDeactivating = in_array($rawStatus, ['inactive', '0', 'false']);
        if ($isDeactivating && $isDefaultAdmin) {
            return $this->error('The default super admin cannot be deactivated.', 403);
        }

        $canonicalStatus = $isDeactivating ? Constants::USER_STATUS_INACTIVE : Constants::USER_STATUS_ACTIVE;
        $user->status = $canonicalStatus;
        $user->save();

        return $this->success($user, 'Employee status updated successfully.');
    }

    /**
     * Upload an employee document
     */
    public function uploadDocument(Request $request, $id)
    {
        $user = User::find($id);
        if (!$user) {
            return $this->error('Employee not found.', 404);
        }

        $validator = Validator::make($request->all(), [
            'document'      => 'required|file|max:10240', // max 10MB
            'document_type' => 'required|string|max:100', // Passport, Emirates ID, Visa, Labour Contract, NDA, Employment Contract, Certificates, Medical Insurance, Other
            'title'         => 'nullable|string|max:255',
            'expiry_date'   => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 422, $validator->errors());
        }

        try {
            $file = $request->file('document');
            $folder = 'employee-documents/' . $user->id;

            // Upload via FileStorageService or local fallback
            try {
                $filePath = $this->fileStorage->uploadPrivateFile($file, $folder);
            } catch (\Exception $e) {
                // Local fallback if s3 private is not configured
                $storedPath = $file->store($folder, 'public');
                $filePath = $storedPath;
            }

            $existingDoc = null;
            if (in_array($request->document_type, ['Passport', 'Emirates ID', 'Visa'])) {
                $existingDoc = EmployeeDocument::where('user_id', $user->id)
                    ->where('document_type', $request->document_type)
                    ->first();
            }

            if ($existingDoc) {
                $existingDoc->update([
                    'title'         => $request->title ?? $file->getClientOriginalName(),
                    'file_path'     => $filePath,
                    'file_name'     => $file->getClientOriginalName(),
                    'file_size'     => $file->getSize(),
                    'file_type'     => $file->getClientMimeType(),
                    'expiry_date'   => $request->expiry_date ?? $existingDoc->expiry_date,
                    'uploaded_by'   => auth('api')->id(),
                ]);
                $document = $existingDoc;
            } else {
                $document = EmployeeDocument::create([
                    'user_id'       => $user->id,
                    'document_type' => $request->document_type,
                    'title'         => $request->title ?? $file->getClientOriginalName(),
                    'file_path'     => $filePath,
                    'file_name'     => $file->getClientOriginalName(),
                    'file_size'     => $file->getSize(),
                    'file_type'     => $file->getClientMimeType(),
                    'expiry_date'   => $request->expiry_date,
                    'uploaded_by'   => auth('api')->id(),
                ]);
            }

            // Sync with user_meta for backward compatibility
            $typeToMetaKey = [
                'Passport'            => '_passportFile',
                'Emirates ID'         => '_emiratesIdFile',
                'Visa'                => '_visaFile',
                'Labour Contract'     => '_labourContractFile',
                'NDA'                 => '_ndaFile',
                'Employment Contract' => '_employmentContractFile',
                'Certificates'        => '_certificatesFile',
                'Medical Insurance'   => '_insuranceFile',
            ];

            if (isset($typeToMetaKey[$request->document_type])) {
                $metaKey = $typeToMetaKey[$request->document_type];
                UserMeta::updateOrCreate(
                    ['userId' => $user->id, 'metaKey' => $metaKey],
                    ['metaValue' => json_encode([[
                        'fileName' => $file->getClientOriginalName(),
                        'filePath' => $document->file_url,
                        'type' => $file->getClientMimeType(),
                    ]])]
                );
            }

            if ($request->filled('expiry_date')) {
                $typeToExpMetaKey = [
                    'Passport'            => '_passportExpiryDate',
                    'Emirates ID'         => '_emiratesIdExpiryDate',
                    'Visa'                => '_visaExpiryDate',
                    'Labour Contract'     => '_labourContractExpiryDate',
                    'NDA'                 => '_ndaExpiryDate',
                    'Employment Contract' => '_employmentContractExpiryDate',
                    'Certificates'        => '_certificatesExpiryDate',
                    'Medical Insurance'   => '_insuranceExpiryDate',
                ];
                if (isset($typeToExpMetaKey[$request->document_type])) {
                    UserMeta::updateOrCreate(
                        ['userId' => $user->id, 'metaKey' => $typeToExpMetaKey[$request->document_type]],
                        ['metaValue' => $request->expiry_date]
                    );
                }
            }

            return $this->success($document, 'Document uploaded successfully.', 201);
        } catch (\Exception $e) {
            return $this->exceptionResponse('HrEmployeeController : uploadDocument', $e);
        }
    }

    /**
     * Delete an employee document
     */
    public function deleteDocument($docId)
    {
        $doc = EmployeeDocument::find($docId);
        if (!$doc) {
            return $this->error('Document not found.', 404);
        }

        try {
            $doc->delete();
            return $this->success([], 'Document deleted successfully.');
        } catch (\Exception $e) {
            return $this->exceptionResponse('HrEmployeeController : deleteDocument', $e);
        }
    }

    /**
     * Leave Balance Report
     */
    public function leaveBalanceReport(Request $request)
    {
        try {
            $query = User::select('id', 'employee_id', 'name', 'department')
                ->with(['meta' => fn ($q) => $q->whereIn('metaKey', ['_annualLeaveAllowed', '_leaveBalance'])])
                ->where('status', 'active');

            if ($request->filled('department') && $request->department !== 'all') {
                $query->where('department', $request->department);
            }

            $users = $query->orderBy('name', 'asc')->get();

            // Calculate used leaves from approved leaves
            $currentYear = Carbon::now()->year;
            $approvedLeaves = DB::table('leaves')
                ->where('status', 'approved')
                ->whereNull('deleted_at')
                ->whereYear('start_date', $currentYear)
                ->groupBy('user_id')
                ->select('user_id', DB::raw('SUM(working_days) as used_days'))
                ->pluck('used_days', 'user_id');

            $data = $users->map(function ($u) use ($approvedLeaves) {
                $used = (float) ($approvedLeaves[$u->id] ?? 0);
                $allowed = (int) ($u->meta?->firstWhere('metaKey', '_annualLeaveAllowed')?->metaValue ?? 30);
                $savedBalance = $u->meta?->firstWhere('metaKey', '_leaveBalance')?->metaValue;
                $balance = $savedBalance !== null ? (float) $savedBalance : max(0, $allowed - $used);
                return [
                    'id'                   => $u->id,
                    'employee_id'          => $u->employee_id,
                    'name'                 => $u->name,
                    'department'           => $u->department,
                    'annual_leave_allowed' => $allowed,
                    'used_leave'           => $used,
                    'leave_balance'        => $balance,
                ];
            });

            return $this->success($data, 'Leave balance report retrieved successfully.');
        } catch (\Exception $e) {
            return $this->exceptionResponse('HrEmployeeController : leaveBalanceReport', $e);
        }
    }

    /**
     * Upcoming Document Expiry Report
     */
    public function documentExpiryReport(Request $request)
    {
        try {
            $days = (int) $request->get('days', 30);
            $targetDate = Carbon::today()->addDays($days)->toDateString();
            $today = Carbon::today()->toDateString();

            // 1. Check UserMeta table for document expiries
            $metaKeyMap = [
                '_visaExpiryDate'           => 'Visa',
                '_passportExpiryDate'       => 'Passport',
                '_emiratesIdExpiryDate'     => 'Emirates ID',
                '_insuranceExpiryDate'      => 'Insurance',
                '_labourContractExpiryDate' => 'Labour Card',
                '_labourCardExpiryDate'     => 'Labour Card',
            ];

            $userMetas = UserMeta::with('user:id,employee_id,name,department')
                ->whereIn('metaKey', array_keys($metaKeyMap))
                ->whereBetween('metaValue', [$today, $targetDate])
                ->get();

            $results = [];

            foreach ($userMetas as $meta) {
                if ($meta->user && $meta->user->status === 'active') {
                    $diff = Carbon::today()->diffInDays(Carbon::parse($meta->metaValue), false);
                    $results[] = [
                        'user_id'       => $meta->user->id,
                        'employee_id'   => $meta->user->employee_id,
                        'name'          => $meta->user->name,
                        'department'    => $meta->user->department,
                        'document_type' => $metaKeyMap[$meta->metaKey] ?? 'Document',
                        'expiry_date'   => Carbon::parse($meta->metaValue)->toDateString(),
                        'days_left'     => max(0, $diff),
                    ];
                }
            }

            // 2. Check employee_documents table
            $expiringDocs = EmployeeDocument::with('user:id,employee_id,name,department')
                ->whereBetween('expiry_date', [$today, $targetDate])
                ->get();

            foreach ($expiringDocs as $doc) {
                if ($doc->user && $doc->user->status === 'active') {
                    $diff = Carbon::today()->diffInDays(Carbon::parse($doc->expiry_date), false);
                    $results[] = [
                        'user_id'       => $doc->user->id,
                        'employee_id'   => $doc->user->employee_id,
                        'name'          => $doc->user->name,
                        'department'    => $doc->user->department,
                        'document_type' => $doc->document_type . ($doc->title ? " ({$doc->title})" : ''),
                        'expiry_date'   => Carbon::parse($doc->expiry_date)->toDateString(),
                        'days_left'     => max(0, $diff),
                    ];
                }
            }

            // Sort by days_left asc
            usort($results, fn($a, $b) => $a['days_left'] <=> $b['days_left']);

            return $this->success($results, 'Document expiry report retrieved successfully.');
        } catch (\Exception $e) {
            return $this->exceptionResponse('HrEmployeeController : documentExpiryReport', $e);
        }
    }
}

