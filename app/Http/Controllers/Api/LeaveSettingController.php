<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LeaveSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LeaveSettingController extends Controller
{
    /**
     * Display a listing of the leave settings.
     */
    public function index(Request $request)
    {
        $query = LeaveSetting::query();

        if ($request->has('search') && !empty($request->search)) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        if ($request->input('take_all') === 'all') {
            $items = $query->orderBy('id', 'desc')->get();
            return response()->json([
                'success' => true,
                'data' => $items,
                'meta' => [
                    'total' => $items->count(),
                    'page' => 1,
                    'per_page' => $items->count()
                ]
            ]);
        }

        $perPage = $request->input('per_page', 15);
        $leaveSettings = $query->orderBy('id', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $leaveSettings->items(),
            'meta' => [
                'total' => $leaveSettings->total(),
                'page' => $leaveSettings->currentPage(),
                'per_page' => $leaveSettings->perPage()
            ]
        ]);
    }

    /**
     * Store a newly created leave setting in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'days' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->only(['name', 'days', 'is_active']);
        $data['days'] = $data['days'] ?? 0;
        $data['is_active'] = $data['is_active'] ?? true;

        $leaveSetting = LeaveSetting::create($data);

        if (auth('api')->user()) {
            logUserActivity([
                'userId' => auth('api')->id(),
                'action' => 'create',
                'module' => 'Leave Settings',
                'description' => 'User created a new Leave Setting',
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Leave Setting created successfully.',
            'data' => $leaveSetting
        ], 201);
    }

    /**
     * Update the specified leave setting in storage.
     */
    public function update(Request $request, $id)
    {
        $leaveSetting = LeaveSetting::find($id);

        if (!$leaveSetting) {
            return response()->json([
                'success' => false,
                'message' => 'Leave Setting not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'days' => 'sometimes|nullable|integer|min:0',
            'is_active' => 'sometimes|nullable|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->only(['name', 'days', 'is_active']);
        $leaveSetting->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Leave Setting updated successfully.',
            'data' => $leaveSetting
        ]);
    }

    /**
     * Remove the specified leave setting from storage.
     */
    public function destroy($id)
    {
        $leaveSetting = LeaveSetting::find($id);

        if (!$leaveSetting) {
            return response()->json([
                'success' => false,
                'message' => 'Leave Setting not found'
            ], 404);
        }

        $leaveSetting->delete();

        return response()->json([
            'success' => true,
            'message' => 'Leave Setting deleted successfully.'
        ]);
    }

    /**
     * Remove multiple leave settings.
     */
    public function bulkDelete(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:leave_settings,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        LeaveSetting::whereIn('id', $request->ids)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Leave Settings deleted successfully.'
        ]);
    }
}
