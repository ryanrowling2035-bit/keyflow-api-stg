<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    use HasFactory;

    protected $table = 'attendances';

    public const STATUS_PRESENT = 'present';
    public const STATUS_LATE = 'late';
    public const STATUS_ABSENT = 'absent';
    public const STATUS_HALF_DAY = 'half_day';
    public const STATUS_ON_LEAVE = 'on_leave';
    public const STATUS_HOLIDAY = 'holiday';
    public const STATUS_WEEKEND = 'weekend';

    public const STATUSES = [
        self::STATUS_PRESENT,
        self::STATUS_LATE,
        self::STATUS_ABSENT,
        self::STATUS_HALF_DAY,
        self::STATUS_ON_LEAVE,
        self::STATUS_HOLIDAY,
        self::STATUS_WEEKEND,
    ];

    protected $fillable = [
        'user_id',
        'employee_name',
        'employee_code',
        'designation',
        'department',
        'profile_photo',
        'date',
        'status',
        'check_in',
        'check_out',
        'work_location',
        'working_hours',
        'notes',
        'updated_by',
        'check_in_time',
        'check_out_time',
        'marked_by',
        'remarks',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    /**
     * Relationship to User
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function marker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_by');
    }
}
