<?php

namespace App\Http\Controllers\Api;

use App\Constants\Permissions;
use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Traits\ApiResponse;

class PermissionController extends Controller
{
    use ApiResponse;

    public function index()
    {
        try {
            $allPermissions = Permission::all()->keyBy('name');
            $response = [];

            foreach (Permissions::MAP as $role => $modules) {
                $roleGroupedPermissions = [];

                foreach ($modules as $moduleKey => $moduleData) {
                    $modulePermission = ['module' => $moduleData['slug']];

                    foreach ($moduleData['roles'] as $permissionName) {
                        if (! isset($allPermissions[$permissionName])) {
                            continue;
                        }

                        $action = strtolower($allPermissions[$permissionName]->action ?? '');

                        if (in_array($action, ['view', 'create', 'edit', 'delete', 'approve'])) {
                            $modulePermission[$action] = true;
                        }
                    }

                    if (count($modulePermission) > 1) {
                        $roleGroupedPermissions[] = $modulePermission;
                    }
                }

                $response[$role] = $roleGroupedPermissions;
            }

            return $this->success(
                $response,
                'Role-wise permissions retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->exceptionResponse('PermissionController : index', $e);
        }
    }
}
