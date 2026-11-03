<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Leave extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'user_id',
        'staff_name',
        'leave_type',
        'start_date',
        'end_date',
        'last_working_day',
        'return_date',
        'working_days',
        'reason',
        'has_manager_approved',
        'handover_to',
        'has_doctor_note',
        'attachment_url',
        'status',
        'rejection_reason',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'cancelled_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'last_working_day' => 'date',
        'return_date' => 'date',
        'working_days' => 'float',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    protected $appends = ['employee'];

    public function getEmployeeAttribute()
    {
        return $this->user;
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejecter()
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function leaveTypeDetails()
    {
        return $this->belongsTo(LeaveSetting::class, 'leave_type');
    }
}

