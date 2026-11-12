<?php

namespace App\Http\Resources\RoleResource;

use App\Constants\Constants;
use Illuminate\Http\Resources\Json\JsonResource;

class GetRoleResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'status' => $this->status,
            'statusLabel' => $this->status ? (Constants::ROLE_STATUS[$this->status] ?? $this->status) : null,
            'description' => $this->description,
            'createdAt' => convertToTimezone($this->created_at),
            'createdBy' => $this->creator ? [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ] : null,
            'permissions' => $this->whenLoaded('permissions', function () {
                $groupedPermissions = $this->permissions->groupBy(function ($permission) {
                    return strtolower($permission->module ?? '');
                });

                $permissionsArray = [];

                foreach ($groupedPermissions as $moduleKey => $permissions) {
                    $module = $permissions->first()->module ?? $moduleKey;

                    $modulePermissions = [
                        'module' => $module,
                    ];

                    foreach ($permissions as $permission) {
                        $action = strtolower($permission->action ?? '');

                        if (in_array($action, ['view', 'create', 'edit', 'delete', 'approve'])) {
                            $modulePermissions[$action] = true;
                        }
                    }

                    $permissionsArray[] = $modulePermissions;
                }

                return $permissionsArray;
            }),
        ];
    }
}
