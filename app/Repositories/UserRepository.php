<?php

namespace App\Repositories;

use App\Constants\Constants;
use App\Models\User;
use Illuminate\Http\Request;

class UserRepository
{
    public function getAll(Request $request)
    {
        $perPage = $request->filled('per_page') && $request->per_page > 0
            ? $request->per_page
            : 10;

        $query = User::with(['creator', 'role', 'meta', 'teams', 'employeeDocuments']);

        if (isAgent()) {
            $query->where('createdBy', user()->id);
        }
        if ($request->filled('search')) {
            $search = $request->search;

            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('email', 'LIKE', "%{$search}%")
                    ->orWhere('phone', 'LIKE', "%{$search}%")
                    ->orWhereRaw('LOWER(status) LIKE ?', ['%'.strtolower($search).'%'])
                    ->orWhereHas('role', function ($rq) use ($search) {
                        $rq->where('name', 'LIKE', "%{$search}%");
                    });
            });
        }

        if ($request->filled('agentType')) {
            $query->where('agentType', $request->agentType);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('teamId')) {
            $query->whereHas('teams', function ($q) use ($request) {
                $q->where('assign_team_members.teamId', $request->teamId);
            });
        }

        if ($request->filled('type')) {
            if (is_numeric($request->type)) {
                $query->where('roleId', $request->type);
            } else {
                $query->whereHas('role', function ($q) use ($request) {
                    $q->where('name', $request->type);
                });
            }

            if ($request->type === Constants::AGENT) {
                $query->withCount(['enquiry', 'property']);
            }
        }

        if ($request->filled('excludeUserRoles')) {
            $excludeRoles = array_map('trim', explode(',', $request->excludeUserRoles));
            $query->whereDoesntHave('role', function ($q) use ($excludeRoles) {
                $q->whereIn('name', $excludeRoles);
            });
        }

        if ($request->filled('id')) {
            $priorityId = $request->id;

            $query->orderByRaw(
                'CASE WHEN id = ? THEN 0 ELSE 1 END',
                [$priorityId]
            );
        }

        return $query
            ->orderBy('id', 'desc')
            ->paginate($perPage);
    }

    public function findById($id)
    {
        return User::with(['creator', 'role', 'meta', 'teams', 'employeeDocuments'])->find($id);
    }

    public function create(array $data)
    {
        return User::create($data);
    }

    public function update(User $user, array $data)
    {
        $user->update($data);

        return $user;
    }

    public function delete(User $user)
    {
        return $user->delete();
    }
}
