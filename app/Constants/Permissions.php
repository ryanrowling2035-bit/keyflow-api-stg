<?php

namespace App\Constants;

class Permissions
{
    const MAP = [
        'SuperAdmin' => [
            'dashboard' => [
                'name' => 'Dashboard',
                'slug' => 'dashboard',
                'roles' => ['view dashboard'],
            ],
            'users' => [
                'name' => 'Users',
                'slug' => 'users',
                'roles' => ['view users', 'create users', 'edit users', 'delete users'],
            ],
            'roles' => [
                'name' => 'Roles',
                'slug' => 'roles',
                'roles' => ['view roles', 'create roles', 'edit roles', 'delete roles'],
            ],
            'agents' => [
                'name' => 'Agents',
                'slug' => 'agents',
                'roles' => ['view agents', 'create agents', 'edit agents', 'delete agents'],
            ],
            'properties' => [
                'name' => 'Properties',
                'slug' => 'properties',
                'roles' => ['view properties', 'create properties', 'edit properties', 'delete properties'],
            ],
            'enquiries' => [
                'name' => 'Enquiries',
                'slug' => 'enquiries',
                'roles' => ['view enquiries', 'create enquiries', 'edit enquiries', 'delete enquiries'],
            ],
            'offers' => [
                'name' => 'Offers',
                'slug' => 'offers',
                'roles' => ['view offers', 'create offers', 'edit offers', 'delete offers'],
            ],
            'closing' => [
                'name' => 'Closing',
                'slug' => 'closing',
                'roles' => ['view closing', 'create closing', 'edit closing', 'delete closing'],
            ],
            'contacts' => [
                'name' => 'Contacts',
                'slug' => 'contacts',
                'roles' => ['view contacts', 'create contacts', 'edit contacts', 'delete contacts'],
            ],
            'tasks' => [
                'name' => 'Tasks',
                'slug' => 'tasks',
                'roles' => ['view tasks', 'create tasks', 'edit tasks', 'delete tasks'],
            ],
            'notifications' => [
                'name' => 'Notifications',
                'slug' => 'notifications',
                'roles' => ['view notifications', 'delete notifications'],
            ],
            'marketing' => [
                'name' => 'Marketing',
                'slug' => 'marketing',
                'roles' => ['view marketing', 'create marketing', 'edit marketing', 'delete marketing'],
            ],
            'boost' => [
                'name' => 'Boost Center',
                'slug' => 'boost',
                'roles' => ['view boost', 'create boost', 'edit boost', 'delete boost'],
            ],
            'reports' => [
                'name' => 'Reports',
                'slug' => 'reports',
                'roles' => ['view reports'],
            ],
            'settings' => [
                'name' => 'Settings',
                'slug' => 'settings',
                'roles' => ['view settings', 'create settings'],
            ],
            'calendar' => [
                'name' => 'Calendar',
                'slug' => 'calendar',
                'roles' => ['view calendar', 'create calendar', 'edit calendar', 'delete calendar'],
            ],
            'outlookSync' => [
                'name' => 'Outlook Sync',
                'slug' => 'outlook_sync',
                'roles' => ['view outlook_sync', 'create outlook_sync', 'edit outlook_sync', 'delete outlook_sync'],
            ],
            'teams' => [
                'name' => 'Team Management',
                'slug' => 'teams',
                'roles' => ['view teams', 'create teams', 'edit teams', 'delete teams'],
            ],
            'documents' => [
                'name' => 'Documents',
                'slug' => 'documents',
                'roles' => ['view documents', 'create documents', 'edit documents', 'delete documents'],
            ],
            'emailTemplates' => [
                'name' => 'Email Templates',
                'slug' => 'email_templates',
                'roles' => ['view email_templates', 'edit email_templates'],
            ],
            // Audit log — read-only. Activity records are system-generated, so
            // there is nothing to create, edit or delete; only a view gate.
            'userActivity' => [
                'name' => 'User Activity',
                'slug' => 'user_activity',
                'roles' => ['view user_activity'],
            ],
            'hrDashboard' => [
                'name' => 'HR Dashboard',
                'slug' => 'hr_dashboard',
                'roles' => ['view hr_dashboard'],
            ],
            'hrEmployees' => [
                'name' => 'Employee Directory',
                'slug' => 'hr_employees',
                'roles' => ['view hr_employees', 'create hr_employees', 'edit hr_employees', 'delete hr_employees'],
            ],
            'leaves' => [
                'name' => 'Leave Management',
                'slug' => 'leaves',
                'roles' => ['view leaves', 'create leaves', 'edit leaves', 'delete leaves', 'approve leaves'],
            ],
            'attendance' => [
                'name' => 'Attendance',
                'slug' => 'attendance',
                'roles' => ['view attendance', 'create attendance', 'edit attendance'],
            ],
            'leaveSettings' => [
                'name' => 'Leave Settings',
                'slug' => 'leave_settings',
                'roles' => ['view leave_settings', 'create leave_settings', 'edit leave_settings', 'delete leave_settings'],
            ],
        ],

        'Agent' => [
            'properties' => [
                'name' => 'Properties',
                'slug' => 'properties',
                'roles' => ['view properties', 'create properties', 'edit properties', 'delete properties'],
            ],
            'enquiries' => [
                'name' => 'Enquiries',
                'slug' => 'enquiries',
                'roles' => ['view enquiries', 'create enquiries', 'edit enquiries', 'delete enquiries'],
            ],
            'contacts' => [
                'name' => 'Contacts',
                'slug' => 'contacts',
                'roles' => ['view contacts', 'create contacts', 'edit contacts', 'delete contacts'],
            ],
            'tasks' => [
                'name' => 'Tasks',
                'slug' => 'tasks',
                'roles' => ['view tasks', 'create tasks', 'edit tasks', 'delete tasks'],
            ],
            'notifications' => [
                'name' => 'Notifications',
                'slug' => 'notifications',
                'roles' => ['view notifications', 'delete notifications'],
            ],
            'calendar' => [
                'name' => 'Calendar',
                'slug' => 'calendar',
                'roles' => ['view calendar', 'create calendar', 'edit calendar'],
            ],
            'marketing' => [
                'name' => 'Marketing',
                'slug' => 'marketing',
                'roles' => ['view marketing'],
            ],
            'outlookSync' => [
                'name' => 'Outlook Sync',
                'slug' => 'outlook_sync',
                'roles' => ['view outlook_sync', 'create outlook_sync'],
            ],
            'offers' => [
                'name' => 'Offers',
                'slug' => 'offers',
                'roles' => ['view offers', 'create offers', 'edit offers', 'delete offers'],
            ],
            'closing' => [
                'name' => 'Closing',
                'slug' => 'closing',
                'roles' => ['view closing', 'create closing', 'edit closing', 'delete closing'],
            ],
            'leaves' => [
                'name' => 'Leave Management',
                'slug' => 'leaves',
                'roles' => ['view leaves', 'create leaves'],
            ],
        ],

        // HR manages user records & full HR Operations suite.
        'HR' => [
            'users' => [
                'name' => 'Users',
                'slug' => 'users',
                'roles' => ['view users', 'create users', 'edit users', 'delete users'],
            ],
            'hrDashboard' => [
                'name' => 'HR Dashboard',
                'slug' => 'hr_dashboard',
                'roles' => ['view hr_dashboard'],
            ],
            'hrEmployees' => [
                'name' => 'Employee Directory',
                'slug' => 'hr_employees',
                'roles' => ['view hr_employees', 'create hr_employees', 'edit hr_employees', 'delete hr_employees'],
            ],
            'leaves' => [
                'name' => 'Leave Management',
                'slug' => 'leaves',
                'roles' => ['view leaves', 'create leaves', 'edit leaves', 'delete leaves', 'approve leaves'],
            ],
            'attendance' => [
                'name' => 'Attendance',
                'slug' => 'attendance',
                'roles' => ['view attendance', 'create attendance', 'edit attendance'],
            ],
            'leaveSettings' => [
                'name' => 'Leave Settings',
                'slug' => 'leave_settings',
                'roles' => ['view leave_settings', 'create leave_settings', 'edit leave_settings', 'delete leave_settings'],
            ],
        ],
    ];

    public static function all(): array
    {
        $permissions = [];

        foreach (self::MAP as $role => $modules) {
            foreach ($modules as $moduleKey => $moduleData) {
                if (isset($moduleData['roles']) && is_array($moduleData['roles'])) {
                    foreach ($moduleData['roles'] as $permissionName) {
                        $parts = explode(' ', $permissionName, 2);
                        $action = $parts[0] ?? '';
                        $module = $parts[1] ?? $moduleData['slug'] ?? $moduleKey;

                        $permissions[$permissionName] = [
                            'name' => $permissionName,
                            'module' => $module,
                            'action' => $action,
                        ];
                    }
                }
            }
        }

        return array_values($permissions);
    }

    public static function getByRole(string $roleKey): array
    {
        return self::MAP[$roleKey] ?? [];
    }

    public static function getPermissionNamesByRole(string $roleKey): array
    {
        $permissions = [];
        $rolePermissions = self::getByRole($roleKey);

        foreach ($rolePermissions as $moduleKey => $moduleData) {
            if (isset($moduleData['roles']) && is_array($moduleData['roles'])) {
                foreach ($moduleData['roles'] as $permissionName) {
                    $permissions[] = $permissionName;
                }
            }
        }

        return $permissions;
    }

    public static function roleHasPermission(string $roleKey, string $permission): bool
    {
        $permissionNames = self::getPermissionNamesByRole($roleKey);

        return in_array($permission, $permissionNames);
    }

    /**
     * Map frontend module names to slugs
     */
    public static function getModuleSlugMap(): array
    {
        return [
            'Dashboard' => 'dashboard',
            'Manage Admin Users' => 'users',
            'Manage Agents' => 'agents',
            'Real Estate' => 'properties',
            'Manage Enquiries' => 'enquiries',
            'Manage Contacts' => 'contacts',
            'Tasks' => 'tasks',
            'Notifications' => 'notifications',
            'Marketing Assets' => 'marketing',
            'Boost Center' => 'boost',
            'Reports' => 'reports',
            'Settings' => 'settings',
            'Calendar' => 'calendar',
            'Outlook Sync' => 'outlook_sync',
            'Team Management' => 'teams',
            'Documents' => 'documents',
            'Email Templates' => 'email_templates',
            'User Activity' => 'user_activity',
            'HR Dashboard' => 'hr_dashboard',
            'Employee Directory' => 'hr_employees',
            'Leave Management' => 'leaves',
            'Leave Requests' => 'leaves',
            'Attendance' => 'attendance',
            'Leave Settings' => 'leave_settings',
        ];
    }

    public static function convertRequestPermissionsToNames(array $requestPermissions): array
    {
        $permissionNames = [];
        $moduleSlugMap = self::getModuleSlugMap();

        $validSlugs = array_values($moduleSlugMap);

        foreach ($requestPermissions as $permission) {
            $moduleName = $permission['module'] ?? '';

            if (isset($moduleSlugMap[$moduleName])) {
                $slug = $moduleSlugMap[$moduleName];
            } elseif (in_array($moduleName, $validSlugs, true)) {
                $slug = $moduleName;
            } else {
                $slug = strtolower(str_replace(' ', '_', $moduleName));
            }

            $actionMap = [
                'view' => 'view',
                'create' => 'create',
                'edit' => 'edit',
                'delete' => 'delete',
                'approve' => 'approve',
            ];

            foreach ($actionMap as $actionKey => $actionValue) {
                if (isset($permission[$actionKey]) && $permission[$actionKey] === true) {
                    $permissionNames[] = "{$actionValue} {$slug}";
                }
            }
        }

        return $permissionNames;
    }
}
