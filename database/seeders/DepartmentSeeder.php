<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $defaultDepartments = [
            'Sales',
            'Marketing',
            'Operations',
            'Human Resources',
            'Finance & Accounts',
            'Administration',
            'IT & Support',
        ];

        foreach ($defaultDepartments as $name) {
            Department::firstOrCreate(
                ['name' => $name],
                ['is_active' => true]
            );
        }
    }
}

