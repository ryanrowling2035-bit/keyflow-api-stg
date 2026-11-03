<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Department;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    public function index(Request $request)
    {
        if (auth('api')->user()) {
            logUserActivity([
                'userId' => auth('api')->user()->id,
                'action' => 'view',
                'module' => 'Departments',
                'moduleId' => null,
                'description' => 'User viewed Departments list',
            ]);
        }

        if (Department::count() === 0) {
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

        $query = Department::query();

        if ($request->has('is_active') && $request->is_active !== null && $request->is_active !== '') {
            $isActive = filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($isActive !== null) {
                $query->where('is_active', $isActive);
            }
        }

        $departments = $query->orderBy('name', 'asc')->get();
        return response()->json(['data' => $departments]);
    }

    public function show($id)
    {
        $department = Department::findOrFail($id);
        return response()->json(['data' => $department]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|unique:departments,name',
            'is_active' => 'boolean'
        ]);

        $department = Department::create($request->all());

        if (auth('api')->user()) {
            logUserActivity([
                'userId' => auth('api')->user()->id,
                'action' => 'create',
                'module' => 'Departments',
                'moduleId' => $department->id,
                'description' => 'User created a new Department',
            ]);
        }
        return response()->json(['message' => 'Department created', 'data' => $department], 201);
    }

    public function update(Request $request, $id)
    {
        $department = Department::findOrFail($id);

        $request->validate([
            'name' => 'required|string|unique:departments,name,' . $id,
            'is_active' => 'boolean'
        ]);

        $department->update($request->all());

        if (auth('api')->user()) {
            logUserActivity([
                'userId' => auth('api')->user()->id,
                'action' => 'update',
                'module' => 'Departments',
                'moduleId' => $department->id,
                'description' => 'User updated Department',
            ]);
        }
        return response()->json(['message' => 'Department updated', 'data' => $department]);
    }

    public function destroy($id)
    {
        $department = Department::findOrFail($id);
        $department->delete();
        return response()->json(['message' => 'Department deleted']);
    }

    public function toggleStatus(Request $request, $id)
    {
        $request->validate([
            'is_active' => 'required|boolean'
        ]);

        $department = Department::findOrFail($id);
        $department->is_active = $request->is_active;
        $department->save();

        return response()->json(['message' => 'Department status updated', 'data' => $department]);
    }
}
