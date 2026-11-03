<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $casts = [
        'force_password_change' => 'boolean',
        'isAvailable' => 'boolean',
    ];

    protected $fillable = [
        'roleId',
        'name',
        'email',
        'phone',
        'assignedLeads',
        'activeProperties',
        'lastLogin',
        'password',
        'profilePhoto',
        'thumbnailPath',
        'status',
        'agentCode',
        'userName',
        'agentType',
        'createdBy',
        'teamId',
        'force_password_change',
        'residencyStatus',
        'website',
        'isAvailable',
        'designation',
        'department',
        'emergency_contact_number',
        'joining_date',
        'date_of_birth',
        'employee_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [];
    }

    public function role()
    {
        return $this->belongsTo(Role::class, 'roleId');
    }

    public function hasRole(string $roleName): bool
    {
        return $this->role && strcasecmp($this->role->name, $roleName) === 0;
    }

    public function getRoleName(): ?string
    {
        return $this->role?->name;
    }

    public function hasPermission(string $permission): bool
    {
        if (! $this->role) {
            return false;
        }
        if ($this->role->name === 'SuperAdmin') {
            return true;
        }

        return $this->role
            ->permissions
            ->contains('name', $permission);
    }

    public function meta()
    {
        return $this->hasMany(UserMeta::class, 'userId');
    }

    public function property()
    {
        return $this->hasMany(Property::class, 'agentId');
    }

    public function contacts()
    {
        return $this->hasMany(Contact::class, 'createdBy');
    }

    public function enquiry()
    {
        return $this->hasMany(Enquiry::class, 'agentId');
    }

    public function follows()
    {
        return $this->hasMany(Follow::class, 'userId');
    }

    public function followedProperties()
    {
        return $this->belongsToMany(Property::class, 'follows', 'userId', 'moduleId')
            ->where('follows.module', 'property')
            ->withTimestamps();
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'createdBy');
    }

    public function teams()
    {
        return $this->belongsToMany(Team::class, 'assign_team_members', 'userId', 'teamId');
    }

    public function outlookConnection()
    {
        return $this->hasOne(OutlookConnection::class, 'userId');
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class, 'userId');
    }

    public function unreadNotifications()
    {
        return $this->hasMany(Notification::class, 'userId')->where('isRead', false);
    }

    public function loginHistories()
    {
        return $this->hasMany(LoginHistory::class, 'userId');
    }

    public function getManagerAttribute()
    {
        // 1. Team-based manager resolution:
        // Employee is assigned to a team; in that team, user with role Manager is the manager
        $teams = $this->relationLoaded('teams') ? $this->teams : $this->teams()->get();
        if ($teams && $teams->isNotEmpty()) {
            foreach ($teams as $t) {
                if ($t->relationLoaded('members')) {
                    $mMember = $t->members->first(function ($member) {
                        return $member->user
                            && (int) $member->user->id !== (int) $this->id
                            && (
                                ($member->user->role && strcasecmp($member->user->role->name, 'Manager') === 0)
                                || $member->user->roleId == 2
                            );
                    });
                    if ($mMember && $mMember->user) {
                        return $mMember->user;
                    }
                }

                $m = $t->users()
                    ->whereHas('role', fn ($q) => $q->where('name', 'Manager'))
                    ->where('users.id', '!=', $this->id)
                    ->where('users.status', 'active')
                    ->first();
                if ($m) {
                    return $m;
                }
            }
        }

        // 2. Fallback: meta _managerId
        $metaManagerId = $this->meta?->firstWhere('metaKey', '_managerId')?->metaValue;
        if (! $metaManagerId && ! $this->relationLoaded('meta')) {
            $metaManagerId = $this->meta()->where('metaKey', '_managerId')->value('metaValue');
        }
        if ($metaManagerId && (int) $metaManagerId !== (int) $this->id) {
            return User::find($metaManagerId);
        }

        return null;
    }

    public function getManagerNameAttribute(): ?string
    {
        return $this->manager?->name;
    }

    public function getManagerIdAttribute(): ?int
    {
        return $this->manager?->id;
    }

    public function employeeDocuments()
    {
        return $this->hasMany(EmployeeDocument::class, 'user_id');
    }

    public function leaves()
    {
        return $this->hasMany(Leave::class, 'user_id');
    }

    public function leaveBalances()
    {
        return $this->hasMany(LeaveBalance::class, 'user_id');
    }

    public function leaveRequests()
    {
        return $this->hasMany(Leave::class, 'user_id');
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class, 'user_id');
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'status' => 'string',
        ];
    }
}
