<?php

use App\Constants\Permissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Migration to insert HR Operations permissions (HR Dashboard, Employee Directory,
 * Leaves, Attendance, and Leave Settings) and grant them to SuperAdmin, HR, and Agent roles.
 * Idempotent: checks for existence before inserting or linking.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = Carbon::now();

        $hrPermissions = [
            // HR Dashboard
            ['name' => 'view hr_dashboard',        'module' => 'hr_dashboard',   'action' => 'view'],

            // Employee Directory
            ['name' => 'view hr_employees',        'module' => 'hr_employees',   'action' => 'view'],
            ['name' => 'create hr_employees',      'module' => 'hr_employees',   'action' => 'create'],
            ['name' => 'edit hr_employees',        'module' => 'hr_employees',   'action' => 'edit'],
            ['name' => 'delete hr_employees',      'module' => 'hr_employees',   'action' => 'delete'],

            // Leave Management
            ['name' => 'view leaves',              'module' => 'leaves',         'action' => 'view'],
            ['name' => 'create leaves',            'module' => 'leaves',         'action' => 'create'],
            ['name' => 'edit leaves',              'module' => 'leaves',         'action' => 'edit'],
            ['name' => 'delete leaves',            'module' => 'leaves',         'action' => 'delete'],
            ['name' => 'approve leaves',           'module' => 'leaves',         'action' => 'approve'],

            // Attendance Tracking
            ['name' => 'view attendance',          'module' => 'attendance',     'action' => 'view'],
            ['name' => 'create attendance',        'module' => 'attendance',     'action' => 'create'],
            ['name' => 'edit attendance',          'module' => 'attendance',     'action' => 'edit'],

            // Leave Settings (Master)
            ['name' => 'view leave_settings',      'module' => 'leave_settings', 'action' => 'view'],
            ['name' => 'create leave_settings',    'module' => 'leave_settings', 'action' => 'create'],
            ['name' => 'edit leave_settings',      'module' => 'leave_settings', 'action' => 'edit'],
            ['name' => 'delete leave_settings',    'module' => 'leave_settings', 'action' => 'delete'],
        ];

        // 1. Insert any missing permissions
        foreach ($hrPermissions as $permission) {
            if (! DB::table('permissions')->where('name', $permission['name'])->exists()) {
                DB::table('permissions')->insert(array_merge($permission, [
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
            }
        }

        // 2. Link permissions to roles according to Permissions::MAP
        $rolesToSync = ['SuperAdmin', 'HR', 'Agent'];

        foreach ($rolesToSync as $roleName) {
            $role = DB::table('roles')->where('name', $roleName)->first();
            if (! $role) {
                continue;
            }

            $moduleMap = Permissions::MAP[$roleName] ?? [];
            $rolePermissionNames = [];

            foreach ($moduleMap as $moduleData) {
                if (! empty($moduleData['roles']) && is_array($moduleData['roles'])) {
                    foreach ($moduleData['roles'] as $permName) {
                        $rolePermissionNames[] = $permName;
                    }
                }
            }

            // Filter for the HR permissions belonging to this role
            $hrNamesForRole = array_intersect(
                $rolePermissionNames,
                array_column($hrPermissions, 'name')
            );

            if (empty($hrNamesForRole)) {
                continue;
            }

            $permissionIds = DB::table('permissions')
                ->whereIn('name', $hrNamesForRole)
                ->pluck('id');

            foreach ($permissionIds as $pId) {
                $alreadyLinked = DB::table('role_permissions')
                    ->where('roleId', $role->id)
                    ->where('permissionId', $pId)
                    ->exists();

                if (! $alreadyLinked) {
                    DB::table('role_permissions')->insert([
                        'roleId' => $role->id,
                        'permissionId' => $pId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        $names = [
            'view hr_dashboard',
            'view hr_employees',
            'create hr_employees',
            'edit hr_employees',
            'delete hr_employees',
            'view leaves',
            'create leaves',
            'edit leaves',
            'delete leaves',
            'approve leaves',
            'view attendance',
            'create attendance',
            'edit attendance',
            'view leave_settings',
            'create leave_settings',
            'edit leave_settings',
            'delete leave_settings',
        ];

        $permissionIds = DB::table('permissions')->whereIn('name', $names)->pluck('id');

        DB::table('role_permissions')->whereIn('permissionId', $permissionIds)->delete();
        DB::table('permissions')->whereIn('name', $names)->delete();
    }
};

