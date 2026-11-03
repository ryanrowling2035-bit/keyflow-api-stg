<?php

namespace App\Http\Resources\UserResource;

use App\Constants\Constants;
use Illuminate\Http\Resources\Json\JsonResource;

class GetUserResource extends JsonResource
{
    public function toArray($request)
    {
        $photoUrl = getImageUrl($this->profilePhoto);

        if (! empty($this->outlookConnection)) {
            $outlookConnection = true;
        } else {
            $outlookConnection = false;
        }

        $userFormattedMeta = [];

        foreach ($this->meta as $meta) {

            $metaValue = $meta->metaValue;

            $decoded = json_decode($metaValue, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $metaValue = $decoded;
            }

            if ($meta->metaKey === '_idCard' && is_array($metaValue)) {
                foreach ($metaValue as &$file) {
                    if (! empty($file['filePath'])) {
                        $file['filePath'] = idCardUrl($file['filePath']);
                    }
                }
                unset($file);
            }

            $userFormattedMeta[$meta->metaKey] = $metaValue;
        }

        $userFormattedMeta['_designation'] = $userFormattedMeta['_designation'] ?? $this->designation;
        $userFormattedMeta['_department'] = $userFormattedMeta['_department'] ?? $this->department;
        $userFormattedMeta['_emergencyContact'] = $userFormattedMeta['_emergencyContact'] ?? $this->emergency_contact_number;
        $userFormattedMeta['_dob'] = $userFormattedMeta['_dob'] ?? $this->date_of_birth;
        $userFormattedMeta['_dateOfBirth'] = $userFormattedMeta['_dateOfBirth'] ?? $this->date_of_birth;
        $userFormattedMeta['_joiningDate'] = $userFormattedMeta['_joiningDate'] ?? $this->joining_date;
        $userFormattedMeta['_joinDate'] = $userFormattedMeta['_joinDate'] ?? $this->joining_date;

        // Fallback: Populate missing document keys in userFormattedMeta from employeeDocuments relation
        $docs = $this->relationLoaded('employeeDocuments') ? $this->employeeDocuments : $this->employeeDocuments()->get();
        if (! empty($docs)) {
            $docTypeToMeta = [
                'Passport'            => ['fileKey' => '_passportFile', 'expKey' => '_passportExpiryDate'],
                'Emirates ID'         => ['fileKey' => '_emiratesIdFile', 'expKey' => '_emiratesIdExpiryDate'],
                'Visa'                => ['fileKey' => '_visaFile', 'expKey' => '_visaExpiryDate'],
                'Labour Contract'     => ['fileKey' => '_labourContractFile', 'expKey' => '_labourContractExpiryDate'],
                'NDA'                 => ['fileKey' => '_ndaFile', 'expKey' => '_ndaExpiryDate'],
                'Employment Contract' => ['fileKey' => '_employmentContractFile', 'expKey' => '_employmentContractExpiryDate'],
                'Certificates'        => ['fileKey' => '_certificatesFile', 'expKey' => '_certificatesExpiryDate'],
                'Medical Insurance'   => ['fileKey' => '_insuranceFile', 'expKey' => '_insuranceExpiryDate'],
            ];

            foreach ($docs as $doc) {
                if (isset($docTypeToMeta[$doc->document_type])) {
                    $metaConfig = $docTypeToMeta[$doc->document_type];
                    $fileKey = $metaConfig['fileKey'];
                    $expKey = $metaConfig['expKey'];

                    if (empty($userFormattedMeta[$fileKey]) && !empty($doc->file_path)) {
                        $userFormattedMeta[$fileKey] = [
                            [
                                'fileName' => $doc->file_name ?? $doc->title,
                                'filePath' => $doc->file_url,
                                'type'     => $doc->file_type,
                            ]
                        ];
                    }

                    if ($expKey && empty($userFormattedMeta[$expKey]) && !empty($doc->expiry_date)) {
                        $userFormattedMeta[$expKey] = $doc->expiry_date->toDateString();
                    }
                }
            }
        }

        return [
            'id' => $this->id,
            'roleId' => $this->roleId ?? null,
            'roleData' => $this->role ? [
                'id' => $this->role->id,
                'name' => $this->role->name,
            ] : null,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => normalizePhoneE164($this->phone),
            'designation' => $this->designation,
            'department' => $this->department,
            'emergencyContact' => $this->emergency_contact_number,
            'emergency_contact_number' => $this->emergency_contact_number,
            'dateOfBirth' => $this->date_of_birth,
            'date_of_birth' => $this->date_of_birth,
            'dob' => $this->date_of_birth,
            'joiningDate' => $this->joining_date,
            'joining_date' => $this->joining_date,
            'joinDate' => $this->joining_date,
            'assignedLeads' => $this->assignedLeads ?? null,
            'activeProperties' => $this->activePropertiesUrl ?? null,
            'lastLogin' => convertToTimezone($this->lastLogin) ?? null,
            'createdAt' => convertToTimezone($this->created_at),
            'createdBy' => $this->createdBy,
            'userName' => $this->userName,
            'agentType' => $this->agentType,
            'agentTypeLabel' => $this->agentType ? (Constants::AGENT_TYPE[$this->agentType] ?? $this->agentType) : null,
            'creatorName' => $this->creator ? $this->creator->name : null,
            'profilePhoto' => $photoUrl,
            'thumbnailPath' => getImageUrl($this->thumbnailPath),
            'status' => $this->status,
            'statusLabel' => $this->status ? (Constants::USER_STATUS[$this->status] ?? $this->status) : null,
            'agentCode' => $this->agentCode,
            'residencyStatus' => $this->residencyStatus,
            'residencyStatusLabel' => $this->residencyStatus ? (Constants::USER_RESIDENCY_STATUS[$this->residencyStatus] ?? $this->residencyStatus) : null,
            'website' => $this->website,
            'isAvailable' => (bool) $this->isAvailable,
            'brokerId' => $userFormattedMeta['_brokerId'] ?? null,
            'userMetaDetails' => $userFormattedMeta,
            'employeeDocuments' => $this->relationLoaded('employeeDocuments') ? $this->employeeDocuments : $this->employeeDocuments()->get(),
            // Intentionally empty: a user can own 20,000+ contacts, so loading the
            // full relation is costly. Kept as [] so the frontend shape holds.
            'contacts' => [],
            'teamId' => $this->teams ?? [],
            'teams' => $this->teams ?? [],
            'outlookConnection' => $outlookConnection,
            'propertiesListingCount' => $this->propertiesListingCount ?? 0,
            'enquiryCount' => $this->enquiryCount ?? 0,
        ];
    }
}
