<?php

namespace App\Services;

use App\Constants\Constants;
use App\Exceptions\UserFacingException;
use App\Models\EmployeeDocument;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\UserMeta;
use App\Repositories\UserRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class UserService
{
    public function __construct(
        protected UserRepository $userRepository,
        protected FileStorageService $fileStorage
    ) {}

    // ================= listUsers =================
    public function listUsers(Request $request)
    {
        return $this->userRepository->getAll($request);
    }

    // ================= generateEmployeeId =================
    public static function generateEmployeeId(): string
    {
        // Find the highest numeric part from existing employee_ids (e.g. EMP-0001 → 1)
        $last = User::whereNotNull('employee_id')
            ->where('employee_id', 'REGEXP', '^EMP-[0-9]+$')
            ->orderByRaw('CAST(SUBSTRING(employee_id, 5) AS UNSIGNED) DESC')
            ->value('employee_id');

        $nextNumber = $last ? ((int) ltrim(substr($last, 4), '0') + 1) : 1;

        return 'EMP-' . str_pad($nextNumber, 2, '0', STR_PAD_LEFT);
    }

    // ================= createUser =================
    public function createUser(array $data, Request $request)
    {

        if ($request->hasFile('profilePhoto')) {
            $file = $request->file('profilePhoto');
            $originalName = time().'_'.str_replace(' ', '', $file->getClientOriginalName());
            $data['profilePhoto'] = $this->fileStorage->uploadPublicFile($file, 'users', $originalName);
            $data['thumbnailPath'] = $this->generateUserThumbnail($file->getPathname(), $originalName);
        }

        $data['password'] = Hash::make($data['password']);

        // Set createdBy to current authenticated user
        if (! isset($data['createdBy'])) {
            $data['createdBy'] = user() ? user()->id : null;
        }

        // Auto-generate employee_id if not provided (starts from EMP-01)
        if (empty($data['employee_id'])) {
            $data['employee_id'] = self::generateEmployeeId();
        }

        $data = normalizePhoneFields($data, ['phone']);

        $user = $this->userRepository->create($data);
        $this->syncUserMeta($user->id, $request);

        return $user->load(['role', 'creator', 'meta', 'teams', 'employeeDocuments']);
    }

    // ================= assignAllPermissionsToRole =================
    protected function assignAllPermissionsToRole(int $roleId): void
    {
        $role = Role::find($roleId);

        if (! $role) {
            return;
        }

        $allPermissionIds = Permission::pluck('id')->toArray();

        if (! empty($allPermissionIds)) {
            $role->permissions()->sync($allPermissionIds);
        }
    }

    public static function getDocumentMapping(): array
    {
        return [
            'passportFile' => [
                'type' => 'Passport',
                'title' => 'Passport',
                'folder' => 'documents/passport',
                'aliases' => ['passport_file'],
                'expiryKeys' => ['passportExpiryDate', 'passport_expiry_date'],
            ],
            'emiratesIdFile' => [
                'type' => 'Emirates ID',
                'title' => 'Emirates ID',
                'folder' => 'documents/emirates-id',
                'aliases' => ['emirates_id_file'],
                'expiryKeys' => ['emiratesIdExpiryDate', 'emirates_id_expiry_date'],
            ],
            'visaFile' => [
                'type' => 'Visa',
                'title' => 'Visa',
                'folder' => 'documents/visa',
                'aliases' => ['visa_file'],
                'expiryKeys' => ['visaExpiryDate', 'visa_expiry_date'],
            ],
            'labourContractFile' => [
                'type' => 'Labour Contract',
                'title' => 'Labour Contract',
                'folder' => 'documents/labour-contract',
                'aliases' => ['labour_contract_file', 'labour_card_file', 'labourContract'],
                'expiryKeys' => ['labourContractExpiryDate', 'labour_contract_expiry_date', 'labourCardExpiryDate', 'labour_card_expiry_date'],
            ],
            'ndaFile' => [
                'type' => 'NDA',
                'title' => 'NDA',
                'folder' => 'documents/nda',
                'aliases' => ['nda_file', 'nda'],
                'expiryKeys' => ['ndaExpiryDate', 'nda_expiry_date'],
            ],
            'employmentContractFile' => [
                'type' => 'Employment Contract',
                'title' => 'Employment Contract',
                'folder' => 'documents/employment-contract',
                'aliases' => ['employment_contract_file', 'employmentContract'],
                'expiryKeys' => ['employmentContractExpiryDate', 'employment_contract_expiry_date'],
            ],
            'certificatesFile' => [
                'type' => 'Certificates',
                'title' => 'Certificates',
                'folder' => 'documents/certificates',
                'aliases' => ['certificates_file', 'certificates'],
                'expiryKeys' => ['certificatesExpiryDate', 'certificates_expiry_date'],
            ],
            'insuranceFile' => [
                'type' => 'Medical Insurance',
                'title' => 'Medical Insurance',
                'folder' => 'documents/medical-insurance',
                'aliases' => ['insurance_file', 'insurance'],
                'expiryKeys' => ['insuranceExpiryDate', 'insurance_expiry_date'],
            ],
        ];
    }

    public function syncEmployeeDocuments(int $userId, Request $request): void
    {
        $docMapping = self::getDocumentMapping();
        $uploaderId = auth('api')->id() ?? (user() ? user()->id : null);

        foreach ($docMapping as $primaryKey => $config) {
            $expiryDate = null;
            foreach ($config['expiryKeys'] as $expKey) {
                if ($request->filled($expKey)) {
                    $expiryDate = $request->input($expKey);
                    break;
                }
            }

            // Check if any matching file was uploaded (primary key or aliases)
            $uploadedFiles = null;
            $allKeys = array_merge([$primaryKey], $config['aliases'] ?? []);
            foreach ($allKeys as $key) {
                if ($request->hasFile($key)) {
                    $raw = $request->file($key);
                    $uploadedFiles = is_array($raw) ? $raw : [$raw];
                    break;
                }
            }

            if (! empty($uploadedFiles)) {
                $storageFolder = $config['folder'];
                $targetDir = storage_path('app/' . $storageFolder);
                if (! file_exists($targetDir)) {
                    mkdir($targetDir, 0755, true);
                }

                $storedFiles = [];

                foreach ($uploadedFiles as $file) {
                    if (! $file) {
                        continue;
                    }

                    $originalName = time() . '_' . preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $file->getClientOriginalName());
                    $fileSize = $file->getSize();
                    $fileMime = $file->getClientMimeType();
                    $fileClientName = $file->getClientOriginalName();

                    $file->move($targetDir, $originalName);

                    $filePath = $storageFolder . '/' . $originalName;
                    $fullUrl = rtrim($request->getSchemeAndHttpHost(), '/') . '/api/' . $filePath;

                    $storedFiles[] = [
                        'fileName' => $originalName,
                        'filePath' => $fullUrl,
                        'type' => $fileMime,
                    ];

                    // Upsert into employee_documents table
                    $existingDoc = EmployeeDocument::where('user_id', $userId)
                        ->where('document_type', $config['type'])
                        ->first();

                    if ($existingDoc) {
                        $existingDoc->update([
                            'title'       => $config['title'],
                            'file_path'   => $filePath,
                            'file_name'   => $fileClientName,
                            'file_size'   => $fileSize,
                            'file_type'   => $fileMime,
                            'expiry_date' => $expiryDate ?? $existingDoc->expiry_date,
                            'uploaded_by' => $uploaderId,
                        ]);
                    } else {
                        EmployeeDocument::create([
                            'user_id'       => $userId,
                            'document_type' => $config['type'],
                            'title'         => $config['title'],
                            'file_path'     => $filePath,
                            'file_name'     => $fileClientName,
                            'file_size'     => $fileSize,
                            'file_type'     => $fileMime,
                            'expiry_date'   => $expiryDate,
                            'uploaded_by'   => $uploaderId,
                        ]);
                    }
                }

                // Sync with user_meta for backward compatibility
                if (isset(Constants::USER_META_KEYS[$primaryKey])) {
                    UserMeta::updateOrCreate(
                        [
                            'userId' => $userId,
                            'metaKey' => Constants::USER_META_KEYS[$primaryKey],
                        ],
                        [
                            'metaValue' => json_encode($storedFiles),
                        ]
                    );
                }
            } elseif ($expiryDate) {
                // If only expiry date was sent (no new file uploaded)
                $existingDoc = EmployeeDocument::where('user_id', $userId)
                    ->where('document_type', $config['type'])
                    ->first();

                if ($existingDoc) {
                    $existingDoc->update(['expiry_date' => $expiryDate]);
                } else {
                    EmployeeDocument::create([
                        'user_id'       => $userId,
                        'document_type' => $config['type'],
                        'title'         => $config['title'],
                        'file_path'     => null,
                        'expiry_date'   => $expiryDate,
                        'uploaded_by'   => $uploaderId,
                    ]);
                }
            }
        }

        // Also handle generic single document upload (if document & document_type supplied directly)
        if ($request->hasFile('document') && $request->filled('document_type')) {
            $file = $request->file('document');
            $docType = $request->input('document_type');
            $docTitle = $request->input('title') ?? $file->getClientOriginalName();
            $expiry = $request->input('expiry_date');

            $folder = 'documents/' . Str::slug($docType);
            $targetDir = storage_path('app/' . $folder);
            if (! file_exists($targetDir)) {
                mkdir($targetDir, 0755, true);
            }

            $originalName = time() . '_' . preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $file->getClientOriginalName());
            $fileSize = $file->getSize();
            $fileMime = $file->getClientMimeType();
            $fileClientName = $file->getClientOriginalName();

            $file->move($targetDir, $originalName);
            $filePath = $folder . '/' . $originalName;

            $existingDoc = EmployeeDocument::where('user_id', $userId)
                ->where('document_type', $docType)
                ->first();

            if ($existingDoc) {
                $existingDoc->update([
                    'title'       => $docTitle,
                    'file_path'   => $filePath,
                    'file_name'   => $fileClientName,
                    'file_size'   => $fileSize,
                    'file_type'   => $fileMime,
                    'expiry_date' => $expiry ?? $existingDoc->expiry_date,
                    'uploaded_by' => $uploaderId,
                ]);
            } else {
                EmployeeDocument::create([
                    'user_id'       => $userId,
                    'document_type' => $docType,
                    'title'         => $docTitle,
                    'file_path'     => $filePath,
                    'file_name'     => $fileClientName,
                    'file_size'     => $fileSize,
                    'file_type'     => $fileMime,
                    'expiry_date'   => $expiry,
                    'uploaded_by'   => $uploaderId,
                ]);
            }
        }
    }

    public function syncUserMeta(int $userId, Request $request): void
    {
        if ($request->hasFile('idCard')) {
            $idCards = [];

            foreach ($request->file('idCard') as $file) {
                $originalName = time().'_'.str_replace(' ', '', $file->getClientOriginalName());
                // ID cards are confidential — stored in the private bucket; only the key is kept.
                $key = $this->fileStorage->uploadPrivateFile($file, 'documents/id-cards', $originalName);

                $idCards[] = [
                    'fileName' => $originalName,
                    'filePath' => $key,
                    'type' => $file->getClientMimeType(),
                ];
            }

            UserMeta::updateOrCreate(
                [
                    'userId' => $userId,
                    'metaKey' => Constants::USER_META_KEYS['idCard'],
                ],
                [
                    'metaValue' => json_encode($idCards),
                ]
            );
        }

        $this->syncEmployeeDocuments($userId, $request);

        $docMapping = self::getDocumentMapping();
        $docKeysToSkip = array_keys($docMapping);

        foreach (Constants::USER_META_KEYS as $requestKey => $metaKey) {
            if ($requestKey === 'idCard' || in_array($requestKey, $docKeysToSkip)) {
                continue;
            }

            if ($request->has($requestKey)) {
                // Only one user can be defaultAdmin at a time
                if ($requestKey === 'defaultAdmin' && $request->input($requestKey)) {
                    $existingAdmin = UserMeta::where('metaKey', $metaKey)
                        ->where('metaValue', '1')
                        ->where('userId', '!=', $userId)
                        ->with('user:id,name')
                        ->first();

                    if ($existingAdmin) {
                        throw new UserFacingException("\"{$existingAdmin->user?->name}\" is already set as the default admin. Please remove them first before assigning a new one.");
                    }
                }

                UserMeta::updateOrCreate(
                    [
                        'userId' => $userId,
                        'metaKey' => $metaKey,
                    ],
                    [
                        'metaValue' => $request->input($requestKey),
                    ]
                );
            }
        }
    }

    // ================= updateUser =================
    public function updateUser(int $id, array $data, Request $request)
    {
        $user = $this->userRepository->findById($id);

        if (! $user) {
            throw new UserFacingException('User not found');
        }

        // Handle profile photo removal
        if (! empty($data['removeProfilePhoto'])) {
            $this->fileStorage->deletePublicByUrl($data['removeProfilePhoto']);
            $this->fileStorage->deletePublicByUrl($user->thumbnailPath);
            $data['profilePhoto'] = null;
            $data['thumbnailPath'] = null;
            unset($data['removeProfilePhoto']);
        }

        if ($request->hasFile('profilePhoto')) {
            $this->fileStorage->deletePublicByUrl($user->profilePhoto);
            $this->fileStorage->deletePublicByUrl($user->thumbnailPath);

            $file = $request->file('profilePhoto');
            $originalName = time().'_'.str_replace(' ', '', $file->getClientOriginalName());
            $data['profilePhoto'] = $this->fileStorage->uploadPublicFile($file, 'users', $originalName);
            $data['thumbnailPath'] = $this->generateUserThumbnail($file->getPathname(), $originalName);
        }

        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
            $data['force_password_change'] = false;
        } else {
            unset($data['password']);
        }

        $data = normalizePhoneFields($data, ['phone']);

        $this->userRepository->update($user, $data);

        $this->syncUserMeta($user->id, $request);

        return $user->fresh()->load(['role', 'creator', 'meta', 'teams', 'employeeDocuments']);
    }

    // ================= deleteUser =================
    public function deleteUser(User $user)
    {
        if ($user->id === 1) {
            throw new UserFacingException('Super Admin cannot be deleted');
        }

        return $this->userRepository->delete($user);
    }

    // ================= updateUserStatus =================
    public function updateUserStatus(User $user, string $status)
    {
        $this->userRepository->update($user, ['status' => $status]);

        return $user->fresh()->load(['role', 'creator', 'meta', 'teams', 'employeeDocuments']);
    }

    protected function generateUserThumbnail(string $sourcePath, string $fileName): string
    {
        $manager = new ImageManager(new Driver);
        $thumbnail = $manager->read($sourcePath);
        $thumbnail->cover(150, 150);

        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $encoded = match ($extension) {
            'png' => $thumbnail->toPng(),
            'webp' => $thumbnail->toWebp(75),
            default => $thumbnail->toJpeg(75),
        };

        return $this->fileStorage->putPublic('users/thumbnails/'.$fileName, (string) $encoded);
    }
}
