<?php

namespace App\Http\Controllers\Api;

use App\Constants\Constants;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource\GetAllUserResource;
use App\Http\Resources\UserResource\GetUserResource;
use App\Models\LoginHistory;
use App\Models\Role;
use App\Models\User;
use App\Models\UserMeta;
use App\Services\MailService;
use App\Services\TeamService;
use App\Services\UserService;
use App\Traits\ApiResponse;
use App\Traits\WordFormatter;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    use ApiResponse, WordFormatter;

    public function __construct(
        protected UserService $userService,
        protected MailService $mailService,
        protected TeamService $teamService
    ) {}

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'login' => 'required|email',
            'password' => 'required|string',
            'context' => 'nullable|in:agent',
        ]);

        if ($validator->fails()) {
            return $this->error(
                'Please check your input.',
                422,
                $validator->errors()
            );
        }

        $throttleKey = 'login|'.Str::lower((string) $request->input('login')).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return $this->error(
                "Too many login attempts. Please try again in {$seconds} seconds.",
                429,
                ['retry_after' => $seconds]
            );
        }

        try {
            $user = User::where('email', $request->login)->first();

            if (! $user) {
                RateLimiter::hit($throttleKey, 60);

                return $this->error('The provided credentials are incorrect.', 401);
            }

            if ($user->status == 'inActive') {
                return $this->error('Your account is inactive. Please contact the administrator.', 403);
            }

            if ($request->context === 'agent' && ! ($user->hasRole('Agent') || $user->hasRole('Manager'))) {
                return $this->error('This login is restricted to agents only.', 403);
            }

            if ($user->force_password_change && $user->hasRole('Agent')) {
                return $this->error(
                    'Your account setup is incomplete. Please set your password using the activation link sent to your registered email address before logging in.',
                    403
                );
            }

            $guard = auth('api');

            // Persist the agent context in the JWT so App-managers stay agent-scoped.
            if ($request->context === 'agent') {
                $guard->claims(['context' => 'agent']);
            }

            if (! $token = $guard->attempt([
                'email' => $request->login,
                'password' => $request->password,
            ])) {
                RateLimiter::hit($throttleKey, 60);

                return $this->error('The provided credentials are incorrect.', 401);
            }

            RateLimiter::clear($throttleKey);

            $user = auth('api')->user();
            $roleName = optional(Role::find($user->roleId))->name;

            $user->update(['lastLogin' => Carbon::now()]);

            LoginHistory::create([
                'userId' => $user->id,
                'ipAddress' => $request->ip(),
                'userAgent' => $request->userAgent() ?? '',
                'os' => $this->detectOS($request->userAgent() ?? ''),
                'device' => $this->detectDevice($request->userAgent() ?? ''),
                'location' => $this->getLocationFromIp($request->ip()),
                'loginType' => $this->detectLoginType($request),
                'loginAt' => Carbon::now(),
            ]);

            $params = [
                'userId' => $user->id,
                'action' => 'logged_in',
                'module' => Constants::MODULE_USER,
                'moduleId' => $user->id,
                'description' => "User logged in: {$user->name}",
            ];
            logUserActivity($params);

            return $this->success([
                'user' => [
                    'id' => $user->id,
                    'roleId' => $user->roleId,
                    'roleName' => $roleName,
                    'name' => $user->name,
                    'email' => $user->email,
                    'date_of_birth' => $user->date_of_birth,
                    'dob' => $user->date_of_birth,
                    'agentType' => $user->agentType,
                    'profilePhoto' => $user->thumbnailPath ? url($user->thumbnailPath) : ($user->profilePhoto ? url($user->profilePhoto) : null),
                ],
                'access_token' => $token,
                'token_type' => 'bearer',
            ], 'Login successful.');
        } catch (\Exception $e) {
            return $this->exceptionResponse('AuthController : login', $e);
        }
    }

    public function users(Request $request)
    {
        try {
            $users = $this->userService->listUsers($request);

            return $this->success(
                GetAllUserResource::collection($users),
                'Users retrieved successfully.'
            );
        } catch (\Exception $e) {
            return $this->exceptionResponse('AuthController : users', $e);
        }
    }

    public function getHrEmployees(Request $request)
    {
        try {
            if (auth()->id()) {
                logUserActivity([
                    'userId' => auth()->id(),
                    'action' => 'View',
                    'module' => 'HR Dashboard',
                    'description' => 'Viewed HR Dashboard / Employee Directory'
                ]);
            }
            $query = \App\Models\User::leftJoin('departments', 'users.department', '=', 'departments.id')
                ->select('users.*', 'departments.name as department_name')
                ->with(['teams.members.user.role', 'role', 'meta']);

            if ($request->filled('status') && $request->status !== 'all') {
                $statusList = is_array($request->status) ? $request->status : explode(',', $request->status);
                $query->where(function ($q) use ($statusList) {
                    foreach ($statusList as $st) {
                        $s = strtolower(trim($st));
                        if (in_array($s, ['active', '1', 'true'])) {
                            $q->orWhereIn('users.status', ['active', 'Active', '1']);
                        } elseif (in_array($s, ['inactive', '0', 'false'])) {
                            $q->orWhereIn('users.status', ['inActive', 'InActive', 'inactive', 'Inactive', '0']);
                        } else {
                            $q->orWhere('users.status', $st);
                        }
                    }
                });
            }

            if ($request->filled('search')) {
                $search = strtolower(trim($request->search));
                $query->where(function ($q) use ($search) {
                    $q->whereRaw('LOWER(users.name) LIKE ?', ["%{$search}%"])
                      ->orWhereRaw('LOWER(users.email) LIKE ?', ["%{$search}%"])
                      ->orWhereRaw('LOWER(users.phone) LIKE ?', ["%{$search}%"]);
                });
            }

            // Department filter
            if ($request->filled('department') && $request->department !== 'all') {
                $deptList = is_array($request->department) ? $request->department : explode(',', $request->department);
                $query->where(function ($q) use ($deptList) {
                    $q->whereIn('users.department', $deptList)
                      ->orWhereIn('departments.name', $deptList);
                });
            }

            // Team filter
            $teamParam = $request->team ?? $request->team_id ?? $request->teamId;
            if (!empty($teamParam) && $teamParam !== 'all') {
                $teamList = is_array($teamParam) ? $teamParam : explode(',', $teamParam);
                $query->whereHas('teams', function ($q) use ($teamList) {
                    $q->whereIn('teams.id', $teamList)->orWhereIn('teams.name', $teamList);
                });
            }

            // Manager filter (match if user's team has this manager, or user's _managerId matches)
            $mgrParam = $request->manager ?? $request->manager_id ?? $request->managerId;
            if (!empty($mgrParam) && $mgrParam !== 'all') {
                $mgrList = is_array($mgrParam) ? $mgrParam : explode(',', $mgrParam);
                $query->where(function ($q) use ($mgrList) {
                    $q->whereHas('teams.members.user', function ($uq) use ($mgrList) {
                        $uq->whereIn('users.id', $mgrList)
                           ->orWhereIn('users.name', $mgrList);
                    })->orWhereHas('meta', function ($mq) use ($mgrList) {
                        $mq->where('metaKey', '_managerId')->whereIn('metaValue', $mgrList);
                    });
                });
            }

            // Joining Date filter
            if ($request->filled('joining_date')) {
                $query->whereDate('users.joining_date', $request->joining_date);
            }

            $users = $query->get();

            $formattedUsers = $users->map(function ($user) {
                $managerUser = null;
                $managerName = null;
                $teamNames = [];

                if ($user->teams && $user->teams->isNotEmpty()) {
                    $teamNames = $user->teams->pluck('name')->filter()->values()->toArray();

                    // Loop through teams assigned to this employee
                    // The user assigned to the same team whose role is Manager is this employee's manager
                    foreach ($user->teams as $team) {
                        if ($team->members) {
                            $mMember = $team->members->first(function ($member) use ($user) {
                                if (! $member->user || (int) $member->user->id === (int) $user->id) {
                                    return false;
                                }
                                $rName = $member->user->role?->name ?? '';
                                return strcasecmp($rName, 'Manager') === 0 || $member->user->roleId == 2;
                            });

                            if ($mMember && $mMember->user) {
                                $managerUser = $mMember->user;
                                $managerName = $managerUser->name;
                                break;
                            }
                        }
                    }
                }

                $metaMap = [];
                if ($user->meta) {
                    foreach ($user->meta as $m) {
                        $metaMap[$m->metaKey] = $m->metaValue;
                    }
                }

                // Fallback to meta _managerId if not resolved from teams
                if (! $managerUser && ! empty($metaMap['_managerId'])) {
                    $metaMgrId = (int) $metaMap['_managerId'];
                    if ($metaMgrId !== (int) $user->id) {
                        $metaMgr = \App\Models\User::find($metaMgrId);
                        if ($metaMgr) {
                            $managerUser = $metaMgr;
                            $managerName = $metaMgr->name;
                        }
                    }
                }

                $dept = $user->department_name ?: $user->department;
                if (empty($dept)) {
                    $rName = strtolower($user->role?->name ?? '');
                    if (str_contains($rName, 'agent') || str_contains($rName, 'manager')) {
                        $dept = 'Sales';
                    } elseif (str_contains($rName, 'hr')) {
                        $dept = 'Human Resources';
                    } elseif (str_contains($rName, 'admin')) {
                        $dept = 'Management';
                    }
                }

                return [
                    'id' => $user->id,
                    'employee_id' => $user->employee_id ?? $user->agentCode ?? ('EMP-' . str_pad($user->id, 4, '0', STR_PAD_LEFT)),
                    'name' => $user->name,
                    'department' => $dept,
                    'department_name' => $dept,
                    'team' => !empty($teamNames) ? implode(', ', $teamNames) : null,
                    'teams' => $user->teams->map(fn ($t) => ['id' => $t->id, 'name' => $t->name]),
                    'designation' => $user->designation,
                    'status' => $user->status,
                    'joining_date' => $user->joining_date,
                    'date_of_birth' => $user->date_of_birth,
                    'dob' => $user->date_of_birth,
                    'email' => $user->email,
                    'manager' => $managerUser ? [
                        'id' => $managerUser->id,
                        'name' => $managerUser->name,
                        'email' => $managerUser->email,
                    ] : null,
                    'manager_name' => $managerName,
                    'manager_id' => $managerUser?->id,
                    'managerName' => $managerName,
                    'phone' => $user->phone,
                    'profilePhoto' => $user->profilePhoto,
                    'userMetaDetails' => $metaMap,
                ];
            });

            return $this->success(
                $formattedUsers,
                'HR Employees retrieved successfully.'
            );
        } catch (\Exception $e) {
            return $this->exceptionResponse('AuthController : getHrEmployees', $e);
        }
    }

    public function addUser(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'email' => 'required|email|unique:users,email',
            'userName' => 'nullable|unique:users,userName',
            'phone' => 'nullable',
            'agentCode' => [
                getRoleNameById($request->roleId) === 'Agent' ? 'required' : 'nullable',
                function ($attribute, $value, $fail) {
                    $email = $this->activeAgentCodeOwnerEmail($value);
                    if ($email !== null) {
                        $fail("This agent code is already assigned to an active user ({$email}).");
                    }
                },
            ],
            'agentType' => 'nullable',
            'profilePhoto' => 'nullable|image|mimes:jpg,jpeg,png',
            'idCard' => 'nullable|array',
            'idCard.*' => 'image|mimes:jpg,jpeg,png',
            'password' => array_merge(['nullable'], passwordRules([
                'name' => $request->input('name'),
                'email' => $request->input('email'),
            ])),
            'status' => 'nullable|string|in:'.implode(',', array_keys(Constants::USER_STATUS)),
            'whatsappNumber' => 'nullable|string',
            'gender' => 'nullable|string',
            'nationality' => 'nullable|string',
            'address' => 'nullable|string',
            'country' => 'nullable|string',
            'state' => 'nullable|string',
            'city' => 'nullable|string',
            'zipCode' => 'nullable|string',
            'licenseNumber' => 'nullable|string',
            'licenseExpiryDate' => 'nullable|string',
            'passportNumber' => 'nullable|string',
            'passportExpiryDate' => 'nullable|string',
            'emiratesIdExpiryDate' => 'nullable|string',
            'insuranceExpiryDate' => 'nullable|string',
            'visaExpiryDate' => 'nullable|string',
            'labourContractExpiryDate' => 'nullable|string',
            'employmentContractExpiryDate' => 'nullable|string',
            'ndaExpiryDate' => 'nullable|string',
            'certificatesExpiryDate' => 'nullable|string',
            'description' => 'nullable|string',
            'residencyStatus' => 'nullable|string|max:50',
            'website' => 'nullable|url',
            'isAvailable' => 'nullable|boolean',
            'roleId' => 'nullable',
            'designation' => 'nullable|string',
            'department' => 'nullable|string',
            'emergency_contact_number' => 'nullable|string',
            'emergencyContact' => 'nullable|string',
            'date_of_birth' => 'nullable|date',
            'dateOfBirth' => 'nullable|date',
            'dob' => 'nullable|date',
            'joining_date' => 'nullable|date',
            'joiningDate' => 'nullable|date',
            'joinDate' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 422, $validator->errors());
        }

        try {
            $plainPassword = $request->password;
            $validatedData = $validator->validated();

            if ($request->filled('emergencyContact') && empty($validatedData['emergency_contact_number'])) {
                $validatedData['emergency_contact_number'] = $request->emergencyContact;
            } elseif ($request->filled('emergency_contact') && empty($validatedData['emergency_contact_number'])) {
                $validatedData['emergency_contact_number'] = $request->emergency_contact;
            }

            if ($request->filled('dateOfBirth') && empty($validatedData['date_of_birth'])) {
                $validatedData['date_of_birth'] = $request->dateOfBirth;
            } elseif ($request->filled('dob') && empty($validatedData['date_of_birth'])) {
                $validatedData['date_of_birth'] = $request->dob;
            }

            if ($request->filled('joiningDate') && empty($validatedData['joining_date'])) {
                $validatedData['joining_date'] = $request->joiningDate;
            } elseif ($request->filled('joinDate') && empty($validatedData['joining_date'])) {
                $validatedData['joining_date'] = $request->joinDate;
            } elseif ($request->filled('join_date') && empty($validatedData['joining_date'])) {
                $validatedData['joining_date'] = $request->join_date;
            }
            if (getRoleNameById($request->roleId) === 'Agent') {
                $validatedData['password'] = $plainPassword;
                $validatedData['status'] = $request->status;
                $validatedData['force_password_change'] = true;
                $user = $this->userService->createUser($validatedData, $request);

                $resetToken = Str::random(64);
                DB::table('password_reset_tokens')->updateOrInsert(
                    ['email' => $user->email],
                    ['token' => $resetToken, 'created_at' => Carbon::now()]
                );
                $resetLink = rtrim(env('FRONTEND_URL', config('app.url')), '/')
                    .'/reset-password?token='.$resetToken
                    .'&n='.$user->name;

                // Email is best-effort: the agent is already saved, so a mail
                // failure must NOT fail the request. Log it and continue.
                try {
                    $resp = $this->mailService->sendEmail(
                        Constants::EMAIL_SLUGS['user']['account_created'],
                        $user->email,
                        [
                            'name' => ucwords($user->name),
                            'email' => $user->email,
                            'reset_password_url' => $resetLink,
                            'company_name' => getCompanyName(),
                        ]
                    );
                } catch (\Throwable $e) {
                    Log::error('AuthController : addUser : sendEmail failed for '.$user->email.': '.$e->getMessage());
                }

                if ($request->has('teamId')) {
                    $teamIds = is_array($request->teamId) ? $request->teamId : [$request->teamId];
                    foreach ($teamIds as $teamId) {
                        $this->teamService->addMembers($teamId, [$user->id], user()->id);
                    }
                    $user->load('teams');
                }

                $params = [
                    'action' => 'created',
                    'module' => Constants::MODULE_USER,
                    'moduleId' => $user->id,
                    'description' => "Created agent: {$user->name}",
                ];
                logUserActivity($params);

                return $this->success(
                    new GetUserResource($user),
                    'Agent created successfully.'
                );
            }

            if (empty($request->password)) {
                return $this->error('Password is required for this user type.', 422);
            }

            $validatedData['password'] = $request->password;
            $validatedData['status'] = $request->status;
            $validatedData['force_password_change'] = false;

            $user = $this->userService->createUser($validatedData, $request);

            if ($request->has('teamId')) {
                $teamIds = is_array($request->teamId) ? $request->teamId : [$request->teamId];
                foreach ($teamIds as $teamId) {
                    $this->teamService->addMembers($teamId, [$user->id], user()->id);
                }
                $user->load('teams');
            }

            $params = [
                'action' => 'created',
                'module' => Constants::MODULE_USER,
                'moduleId' => $user->id,
                'description' => "Created user: {$user->name}",
            ];

            logUserActivity($params);

            return $this->success(
                new GetUserResource($user),
                'User created successfully.'
            );
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'already set as the default admin')) {
                return $this->error($e->getMessage(), 409);
            }

            return $this->exceptionResponse('AuthController : addUser', $e);
        }
    }

    public function updateUser(Request $request, $id)
    {
        // The user being edited — used so the password policy's name/email rule
        // checks against their real details even when those fields aren't resent.
        $targetUser = User::find($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required',
            'email' => [
                'sometimes',
                'required',
                'email',
                Rule::unique('users', 'email')->ignore($id),
            ],
            'userName' => [
                'nullable',
                Rule::unique('users', 'userName')->ignore($id),
            ],
            'phone' => 'nullable',
            'agentCode' => [
                getRoleNameById($request->roleId) === 'Agent' ? 'required' : 'nullable',
                function ($attribute, $value, $fail) use ($id) {
                    $email = $this->activeAgentCodeOwnerEmail($value, (int) $id);
                    if ($email !== null) {
                        $fail("This agent code is already assigned to an active user ({$email}).");
                    }
                },
            ],
            'roleId' => 'nullable',
            'agentType' => 'nullable',
            'profilePhoto' => 'nullable|image|mimes:jpg,jpeg,png,webp',
            'removeProfilePhoto' => 'nullable|string',
            'status' => 'nullable|string|in:'.implode(',', array_keys(Constants::USER_STATUS)),
            'password' => array_merge(['nullable'], passwordRules([
                'name' => $request->input('name', $targetUser?->name),
                'email' => $request->input('email', $targetUser?->email),
            ])),
            'whatsappNumber' => 'nullable|string',
            'gender' => 'nullable|string',
            'nationality' => 'nullable|string',
            'address' => 'nullable|string',
            'country' => 'nullable|string',
            'state' => 'nullable|string',
            'city' => 'nullable|string',
            'zipCode' => 'nullable|string',
            'licenseNumber' => 'nullable|string',
            'licenseExpiryDate' => 'nullable|string',
            'passportNumber' => 'nullable|string',
            'passportExpiryDate' => 'nullable|string',
            'emiratesIdExpiryDate' => 'nullable|string',
            'insuranceExpiryDate' => 'nullable|string',
            'visaExpiryDate' => 'nullable|string',
            'labourContractExpiryDate' => 'nullable|string',
            'employmentContractExpiryDate' => 'nullable|string',
            'ndaExpiryDate' => 'nullable|string',
            'certificatesExpiryDate' => 'nullable|string',
            'description' => 'nullable|string',
            'residencyStatus' => 'nullable|string|max:50',
            'website' => 'nullable|url',
            'isAvailable' => 'nullable|boolean',
            'designation' => 'nullable|string',
            'department' => 'nullable|string',
            'emergency_contact_number' => 'nullable|string',
            'emergencyContact' => 'nullable|string',
            'date_of_birth' => 'nullable|date',
            'dateOfBirth' => 'nullable|date',
            'dob' => 'nullable|date',
            'joining_date' => 'nullable|date',
            'joiningDate' => 'nullable|date',
            'joinDate' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 422, $validator->errors());
        }

        try {
            $validatedData = $validator->validated();

            if ($request->has('emergencyContact')) {
                $validatedData['emergency_contact_number'] = $request->emergencyContact;
            } elseif ($request->has('emergency_contact')) {
                $validatedData['emergency_contact_number'] = $request->emergency_contact;
            }

            if ($request->has('dateOfBirth')) {
                $validatedData['date_of_birth'] = $request->dateOfBirth;
            } elseif ($request->has('dob')) {
                $validatedData['date_of_birth'] = $request->dob;
            }

            if ($request->has('joiningDate')) {
                $validatedData['joining_date'] = $request->joiningDate;
            } elseif ($request->has('joinDate')) {
                $validatedData['joining_date'] = $request->joinDate;
            } elseif ($request->has('join_date')) {
                $validatedData['joining_date'] = $request->join_date;
            }

            unset($validatedData['userName']);
            unset($validatedData['agentCode']);

            // Block deactivating default admin via updateUser
            if (isset($validatedData['status']) && $validatedData['status'] === 'inActive' && $this->isDefaultAdmin((int) $id)) {
                return $this->error('The default super admin cannot be deactivated.', 403);
            }

            $oldUser = User::find($id);
            $oldValues = $oldUser ? $oldUser->only(array_keys($validatedData)) : [];

            $user = $this->userService->updateUser($id, $validatedData, $request);

            if ($request->has('teamId')) {
                $teamIds = is_array($request->teamId) ? $request->teamId : [$request->teamId];

                if ($user->hasRole('Agent')) {
                    $existingTeamIds = $user->teams()->pluck('teams.id')->toArray();
                    $removedTeamIds = array_diff($existingTeamIds, $teamIds);
                    foreach ($removedTeamIds as $oldTeamId) {
                        $this->teamService->removeMembersByUserIds($oldTeamId, [$user->id]);
                    }
                }

                foreach ($teamIds as $teamId) {
                    $this->teamService->addMembers($teamId, [$user->id], user()->id);
                }
                $user->load('teams');
            }

            $params = [
                'action' => 'updated',
                'module' => Constants::MODULE_USER,
                'moduleId' => $user->id,
                'description' => "Updated user: {$user->name}",
                'oldValues' => $oldValues,
                'newValues' => $user->only(array_keys($validatedData)),
            ];
            logUserActivity($params);

            return $this->success(new GetUserResource($user), 'User updated successfully.');
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'already set as the default admin')) {
                return $this->error($e->getMessage(), 409);
            }

            return $this->exceptionResponse('AuthController : updateUser', $e);
        }
    }

    public function getUserById($id)
    {
        try {
            $user = User::with(['enquiry', 'outlookConnection', 'teams' => function ($q) {
                $q->select('teams.id', 'teams.name')->where('teams.status', 'active');
            }])
                ->withCount(['property as propertiesListingCount', 'enquiry as enquiryCount'])
                ->find($id);
            if (! $user) {
                return $this->error('The requested user was not found.', 422);
            }

            return $this->success(new GetUserResource($user), 'User retrieved successfully.');
        } catch (\Exception $e) {
            return $this->exceptionResponse('AuthController : getUserById', $e);
        }
    }

    public function deleteUser($id)
    {
        $user = User::find($id);
        if (! $user) {
            return $this->error('The requested user was not found.', 422);
        }

        if (user()->id === (int) $id) {
            return $this->error('You cannot delete your own account while logged in.', 403);
        }

        if ($this->isDefaultAdmin($user->id)) {
            return $this->error('The default super admin cannot be deleted.', 403);
        }

        try {
            $userName = $user->name;
            $userId = $user->id;
            $this->userService->deleteUser($user);

            $params = [
                'action' => 'deleted',
                'module' => Constants::MODULE_USER,
                'moduleId' => $userId,
                'description' => "Deleted user: {$userName}",
            ];
            logUserActivity($params);

            return $this->success(null, 'User deleted successfully.');
        } catch (\Exception $e) {
            return $this->exceptionResponse('AuthController : deleteUser', $e);
        }
    }

    /**
     * Return the email of the active (non-deleted) user that already owns the given
     * agentCode, or null when the code is free. Optionally ignore a user id (the one
     * being created/updated/activated).
     */
    private function activeAgentCodeOwnerEmail(?string $agentCode, ?int $ignoreId = null): ?string
    {
        if (! $agentCode) {
            return null;
        }

        return User::where('agentCode', $agentCode)
            ->where('status', Constants::USER_STATUS_ACTIVE)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->value('email');
    }

    public function updateUserStatus(Request $request, $id)
    {
        if ($request->has('status')) {
            $s = strtolower(trim((string) $request->status));
            if (in_array($s, ['inactive', '0', 'false'])) {
                $request->merge(['status' => Constants::USER_STATUS_INACTIVE]);
            } elseif (in_array($s, ['active', '1', 'true'])) {
                $request->merge(['status' => Constants::USER_STATUS_ACTIVE]);
            }
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:'.implode(',', array_keys(Constants::USER_STATUS)),
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 422, $validator->errors());
        }

        $user = User::find($id);
        if (! $user) {
            return $this->error('The requested user was not found.', 422);
        }

        if (user()->id === (int) $id) {
            return $this->error('You cannot change your own status.', 403);
        }

        if (! isSuperAdmin()) {
            return $this->error('Only super admin can activate or deactivate users.', 403);
        }

        if ($request->status === 'inActive' && $this->isDefaultAdmin($user->id)) {
            return $this->error('The default super admin cannot be deactivated.', 403);
        }

        if ($request->status === Constants::USER_STATUS_ACTIVE) {
            $email = $this->activeAgentCodeOwnerEmail($user->agentCode, (int) $user->id);
            if ($email !== null) {
                return $this->error("This agent code is already assigned to an active user ({$email}).", 422);
            }
        }

        try {
            $oldStatus = $user->status;
            $updatedUser = $this->userService->updateUserStatus($user, $request->status);

            $params = [
                'action' => 'status_changed',
                'module' => Constants::MODULE_USER,
                'moduleId' => $updatedUser->id,
                'description' => "Changed user status to {$request->status}: {$updatedUser->name}",
                'oldValues' => ['status' => $oldStatus],
                'newValues' => ['status' => $request->status],
            ];
            logUserActivity($params);

            return $this->success(new GetUserResource($updatedUser), 'User status updated successfully.');
        } catch (\Exception $e) {
            return $this->exceptionResponse('AuthController : updateUserStatus', $e);
        }
    }

    public function forgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 422, $validator->errors());
        }

        $user = User::where('email', $request->email)->first();

        if (! $user) {
            return $this->error('User does not exist.', 422);
        }

        $token = Str::random(64);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $request->email],
            ['token' => $token, 'created_at' => Carbon::now()]
        );

        $resetLink = env('FRONTEND_URL', config('app.url'))
            .'/reset-password?token='.$token;

        try {
            $this->mailService->sendEmail(
                Constants::EMAIL_SLUGS['user']['forgot_password'],
                $request->email,
                [
                    'name' => ucwords($user->name),
                    'email' => $request->email,
                    'reset_link' => $resetLink,
                    'company_name' => getCompanyName(),
                ]
            );

            return $this->success(null, 'Password reset link sent successfully.');
        } catch (\Exception $e) {
            return $this->exceptionResponse('AuthController : forgotPassword', $e);
        }
    }

    public function verifyResetToken(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 422, $validator->errors());
        }

        try {
            $reset = DB::table('password_reset_tokens')
                ->where('token', $request->token)
                ->first();

            // The reset page is public (no auth), so it can't read the password
            // policy from /getGlobalConstant — return it here alongside validity.
            $policy = passwordPolicy();

            if (! $reset) {
                return $this->success(
                    ['valid' => false, 'name' => null, 'email' => null, 'passwordPolicy' => $policy],
                    'Invalid reset link.'
                );
            }

            $expireMinutes = config('auth.passwords.users.expire', 60);
            if (now()->diffInMinutes($reset->created_at) > $expireMinutes) {
                DB::table('password_reset_tokens')->where('token', $request->token)->delete();

                return $this->success(
                    ['valid' => false, 'name' => null, 'email' => null, 'passwordPolicy' => $policy],
                    'This reset link has expired. Please request a new one.'
                );
            }

            $user = User::where('email', $reset->email)->first();

            return $this->success([
                'valid' => true,
                'name' => $user->name ?? null,
                'email' => $reset->email,
                'passwordPolicy' => $policy,
            ], 'Reset token is valid.');
        } catch (\Exception $e) {
            return $this->exceptionResponse('AuthController : verifyResetToken', $e);
        }
    }

    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required',
            'password' => 'required',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 422, $validator->errors());
        }

        $reset = DB::table('password_reset_tokens')
            ->where('token', $request->token)
            ->first();

        if (! $reset) {
            return $this->error('Invalid reset link.', 400);
        }

        $expireMinutes = config('auth.passwords.users.expire', 60);
        if (now()->diffInMinutes($reset->created_at) > $expireMinutes) {
            DB::table('password_reset_tokens')->where('token', $request->token)->delete();

            return $this->error('This reset link has expired. Please request a new one.', 400);
        }

        try {
            $user = User::where('email', $reset->email)->firstOrFail();

            // Enforce the password policy now that the account is known, so the
            // name/email rule can check against this user's details.
            $policyValidator = Validator::make($request->all(), [
                'password' => array_merge(['required'], passwordRules([
                    'name' => $user->name,
                    'email' => $user->email,
                ])),
            ]);
            if ($policyValidator->fails()) {
                return $this->error('Validation failed', 422, $policyValidator->errors());
            }

            $user->update([
                'password' => Hash::make($request->password),
                'status' => 'active',
                'force_password_change' => false,
            ]);

            DB::table('password_reset_tokens')
                ->where('token', $request->token)
                ->delete();

            $params = [
                'userId' => $user->id,
                'action' => 'password_reset',
                'module' => Constants::MODULE_USER,
                'moduleId' => $user->id,
                'description' => "Password reset: {$user->name}",
            ];
            logUserActivity($params);

            return $this->success(null, 'Password reset successfully.');
        } catch (\Exception $e) {
            return $this->exceptionResponse('AuthController : resetPassword', $e);
        }
    }

    public function impersonate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'userId' => 'required|integer|exists:users,id',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 422, $validator->errors());
        }

        $currentUser = auth('api')->user();
        if (! $currentUser->role || $currentUser->role->name !== 'SuperAdmin') {
            return $this->error('You do not have permission to impersonate users.', 403);
        }

        $targetUser = User::find($request->userId);

        if (! $targetUser) {
            return $this->error('Target user not found.', 422);
        }

        if ($currentUser->id === $targetUser->id) {
            return $this->error('You cannot impersonate yourself.', 400);
        }

        try {
            $params = [
                'action' => 'impersonated',
                'module' => Constants::MODULE_USER,
                'moduleId' => $targetUser->id,
                'description' => "Impersonated user: {$targetUser->name}",
            ];
            logUserActivity($params);

            $token = auth('api')->login($targetUser);

            $role = Role::find($targetUser->roleId);

            return $this->success([
                'user' => [
                    'id' => $targetUser->id,
                    'roleId' => $targetUser->roleId,
                    'roleName' => $role ? $role->name : null,
                    'name' => $targetUser->name,
                    'email' => $targetUser->email,
                ],
                'access_token' => $token,
                'token_type' => 'bearer',
                'impersonatedBy' => $currentUser->id,
            ], 'User impersonated successfully.');
        } catch (\Exception $e) {
            return $this->exceptionResponse('AuthController : impersonate', $e);
        }
    }

    private function detectOS(string $userAgent): string
    {
        $osPatterns = [
            'Windows NT 10.0' => 'Windows 10/11',
            'Windows NT 6.3' => 'Windows 8.1',
            'Windows NT 6.2' => 'Windows 8',
            'Windows NT 6.1' => 'Windows 7',
            'Macintosh' => 'macOS',
            'Mac OS X' => 'macOS',
            'Android' => 'Android',
            'iPhone' => 'iOS',
            'iPad' => 'iOS',
            'Linux' => 'Linux',
            'Ubuntu' => 'Ubuntu',
            'CrOS' => 'Chrome OS',
        ];

        foreach ($osPatterns as $pattern => $os) {
            if (stripos($userAgent, $pattern) !== false) {
                return $os;
            }
        }

        return 'Unknown';
    }

    private function detectDevice(string $userAgent): string
    {
        if (preg_match('/iPhone/i', $userAgent)) {
            return 'iPhone';
        }
        if (preg_match('/iPad/i', $userAgent)) {
            return 'iPad';
        }
        if (preg_match('/Android.*Mobile/i', $userAgent)) {
            return 'Android Phone';
        }
        if (preg_match('/Android/i', $userAgent)) {
            return 'Android Tablet';
        }
        if (preg_match('/Mobile/i', $userAgent)) {
            return 'Mobile';
        }
        if (preg_match('/Tablet/i', $userAgent)) {
            return 'Tablet';
        }

        return 'Desktop';
    }

    /**
     * Which client the user logged in from — surfaced as lastLoginSource on the dashboard.
     *
     * Trusts the client's own declaration first: X-App-Platform, the same header
     * LogApiRequest::formatHeaders() records alongside X-App-Version. Nothing sends it
     * today, so the user agent remains the working path — but once the apps do, this
     * stops depending on sniffing.
     *
     * Order matters in the fallback: the real clients all look like generic HTTP
     * libraries, so specific signatures are tested before the catch-all ones. Previously
     * every login recorded "API" — the web SPA calls through axios (never as a browser,
     * so the Mozilla/Chrome branch could not fire), the Android app is okhttp, and the
     * iOS app's "ExclusiveLinks/… CFNetwork/… Darwin/…" matched nothing and hit the
     * default.
     */
    private function detectLoginType(Request $request): string
    {
        $platform = strtolower(trim((string) $request->header('x-app-platform')));

        if ($platform !== '') {
            if (str_contains($platform, 'ios')) {
                return 'iOS App';
            }
            if (str_contains($platform, 'android')) {
                return 'Android App';
            }
            if (str_contains($platform, 'web')) {
                return 'Web';
            }

            // An unrecognised platform is still the client telling us what it is —
            // keep it rather than discarding it for a guess from the user agent.
            return ucfirst($platform);
        }

        $userAgent = $request->userAgent() ?? '';

        // API clients — checked first, they are unambiguous.
        if (stripos($userAgent, 'PostmanRuntime') !== false) {
            return 'Postman';
        }
        if (stripos($userAgent, 'Insomnia') !== false) {
            return 'Insomnia';
        }
        if (stripos($userAgent, 'curl') !== false) {
            return 'cURL';
        }

        // Mobile apps. The iOS build sends "ExclusiveLinks/<build> CFNetwork/… Darwin/…";
        // CFNetwork alone also covers other native Apple HTTP clients.
        if (stripos($userAgent, 'ExclusiveLinks') !== false || stripos($userAgent, 'CFNetwork') !== false) {
            return 'iOS App';
        }
        if (stripos($userAgent, 'okhttp') !== false) {
            return 'Android App';
        }

        // The CRM web app talks through axios; a plain browser hit is web too.
        if (preg_match('/^axios/i', $userAgent) || preg_match('/Mozilla|Chrome|Safari|Firefox|Edge|Opera/i', $userAgent)) {
            return 'Web';
        }

        // Anything else scripted (node-fetch, got, python-requests, …) or unrecognised.
        return 'API';
    }

    private function getLocationFromIp(?string $ip): ?string
    {
        if (! $ip || in_array($ip, ['127.0.0.1', '::1'])) {
            return 'Localhost';
        }

        try {
            $response = Http::timeout(3)->get("http://ip-api.com/json/{$ip}", [
                'fields' => 'city,regionName,country,status',
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if (($data['status'] ?? '') === 'success') {
                    return implode(', ', array_filter([
                        $data['city'] ?? null,
                        $data['regionName'] ?? null,
                        $data['country'] ?? null,
                    ]));
                }
            }
        } catch (\Exception $e) {
            Log::warning('Failed to fetch location for IP: '.$ip, ['error' => $e->getMessage()]);
        }

        return null;
    }

    public function selectUsers(Request $request)
    {
        try {
            $query = User::select('id', 'name', 'email', 'agentCode');

            if (! $request->filled('type') || strtolower($request->type) === 'all') {
                if (! $request->filled('type')) {
                    $query->whereDoesntHave('role', function ($q) {
                        $q->where('name', 'Agent');
                    });
                }
            } else {
                $types = explode(',', $request->type);
                $query->whereHas('role', function ($q) use ($types) {
                    $q->whereIn('name', $types);
                });
            }

            if ($request->filled('excludeUserRoles')) {
                $excludeRoles = explode(',', $request->excludeUserRoles);
                $query->whereDoesntHave('role', function ($q) use ($excludeRoles) {
                    $q->whereIn('name', $excludeRoles);
                });
            }

            if ($request->filled('teamId')) {
                $teamId = $request->teamId;
                $query->whereHas('teams', function ($q) use ($teamId) {
                    $q->where('teams.id', $teamId);
                });
            }

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'LIKE', "%{$search}%")
                        ->orWhere('email', 'LIKE', "%{$search}%");
                });
            }

            $users = $query->where('status', 'active')
                ->with('teams:teams.id,teams.name')
                ->orderBy('name')
                ->get()
                ->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'name' => $user->name,
                        'identifier' => $user->name.' - '.$user->email,
                        'agentCode' => $user->agentCode,
                        'teams' => $user->teams->map(fn ($t) => ['id' => $t->id, 'name' => $t->name]),
                        'teamId' => $user->teams->first()?->id,
                        'teamName' => $user->teams->first()?->name,
                    ];
                });

            return $this->success($users, 'Users retrieved successfully.');
        } catch (\Exception $e) {
            return $this->exceptionResponse('AuthController : selectUsers', $e);
        }
    }

    public function getUsersByUserType(Request $request)
    {
        $types = array_filter(explode(',', $request->query('type', '')));

        if (empty($types)) {
            return $this->error('The type query parameter is required.', 422);
        }

        try {
            $query = User::select('id', 'name', 'agentCode', 'email')
                ->with('teams:teams.id,teams.name')
                ->whereHas('role', function ($q) use ($types) {
                    $q->whereIn('name', $types);
                })->where('status', 'active');

            if ($request->filled('search')) {
                $search = $request->search;

                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            }

            $users = $request->filled('per_page')
                ? $query->orderByDesc('id')->paginate((int) $request->per_page)
                : $query->orderByDesc('id')->get();

            return $this->success($users, 'Users retrieved successfully.');
        } catch (\Exception $e) {
            return $this->exceptionResponse('AuthController : getUsersByUserType', $e);
        }
    }

    public function getUsersByRoleId(Request $request, int $id)
    {
        try {
            $query = User::select('id', 'name', 'email', 'agentCode')
                ->where('roleId', $id)
                ->where('status', 'active');

            if (isAgent()) {
                $query->where('createdBy', user()->id);
            }

            if ($request->filled('search')) {
                $search = $request->search;

                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            }

            $users = $request->filled('per_page')
                ? $query->orderByDesc('id')->paginate((int) $request->per_page)
                : $query->orderByDesc('id')->get();

            return $this->success($users, 'Users retrieved successfully.');
        } catch (\Exception $e) {
            return $this->exceptionResponse('AuthController : getUsersByRoleId', $e);
        }
    }

    private function isDefaultAdmin(int $userId): bool
    {
        return UserMeta::where('userId', $userId)
            ->where('metaKey', Constants::USER_META_KEYS['defaultAdmin'])
            ->where('metaValue', '1')
            ->exists();
    }

    public function generateAccessTokens()
    {
        try {
            if (! isSuperAdmin()) {
                return $this->error('Unauthorized.', 403);
            }

            $excludedRoles = Role::whereIn('name', ['Manager', 'Agent', 'HR'])->pluck('id');

            $users = User::whereNotIn('roleId', $excludedRoles)
                ->whereDoesntHave('tokens', fn ($q) => $q->where('name', 'default'))
                ->get();

            if ($users->isEmpty()) {
                return $this->error('All eligible users already have active access tokens.', 409);
            }

            $generated = [];

            foreach ($users as $user) {
                $generated[] = [
                    'userId' => $user->id,
                    'userName' => $user->name,
                    'token' => $user->createToken('default')->plainTextToken,
                ];
            }

            return $this->success($generated, count($generated).' access token(s) generated. Save them now — they cannot be retrieved again.');
        } catch (\Exception $e) {
            return $this->exceptionResponse('AuthController : generateAccessTokens', $e);
        }
    }

    public function issueUserToken(Request $request, int $userId)
    {
        try {
            if (! isSuperAdmin()) {
                return $this->error('Unauthorized.', 403);
            }

            $user = User::find($userId);
            if (! $user) {
                return $this->error('User not found.', 404);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'abilities' => 'sometimes|array',
                'abilities.*' => 'string|max:255',
                'expiresAt' => 'sometimes|nullable|date|after:now',
            ]);

            if ($validator->fails()) {
                return $this->error('Validation failed.', 422, $validator->errors());
            }

            $abilities = $request->input('abilities', ['*']);
            $expiresAt = $request->input('expiresAt') ? Carbon::parse($request->input('expiresAt')) : null;

            $created = $user->createToken($request->input('name'), $abilities, $expiresAt);

            return $this->success([
                'id' => $created->accessToken->id,
                'name' => $created->accessToken->name,
                'abilities' => $created->accessToken->abilities,
                'expires_at' => $created->accessToken->expires_at,
                'plainTextToken' => $created->plainTextToken,
            ], 'Token issued. Save it now — it cannot be retrieved again.');
        } catch (\Exception $e) {
            return $this->exceptionResponse('AuthController : issueUserToken', $e);
        }
    }

    public function listUserTokens(int $userId)
    {
        try {
            if (! isSuperAdmin()) {
                return $this->error('Unauthorized.', 403);
            }

            $user = User::find($userId);
            if (! $user) {
                return $this->error('User not found.', 404);
            }

            $tokens = $user->tokens()
                ->orderByDesc('created_at')
                ->get(['id', 'name', 'abilities', 'last_used_at', 'expires_at', 'created_at']);

            return $this->success($tokens);
        } catch (\Exception $e) {
            return $this->exceptionResponse('AuthController : listUserTokens', $e);
        }
    }

    public function revokeUserToken(int $userId, int $tokenId)
    {
        try {
            if (! isSuperAdmin()) {
                return $this->error('Unauthorized.', 403);
            }

            $user = User::find($userId);
            if (! $user) {
                return $this->error('User not found.', 404);
            }

            $token = $user->tokens()->find($tokenId);
            if (! $token) {
                return $this->error('Token not found for this user.', 404);
            }

            $token->delete();

            return $this->success(null, 'Token revoked.');
        } catch (\Exception $e) {
            return $this->exceptionResponse('AuthController : revokeUserToken', $e);
        }
    }

    public function showDocument(string $path)
    {
        $cleanPath = str_replace(['..', '\\'], '', $path);
        $cleanPath = ltrim($cleanPath, '/');
        if (str_starts_with($cleanPath, 'documents/')) {
            $cleanPath = substr($cleanPath, strlen('documents/'));
        }

        $filePath = storage_path('app/documents/' . $cleanPath);

        if (! file_exists($filePath)) {
            $publicFilePath = storage_path('app/public/documents/' . $cleanPath);
            if (file_exists($publicFilePath)) {
                return response()->file($publicFilePath);
            }
            abort(404);
        }

        return response()->file($filePath);
    }
}
