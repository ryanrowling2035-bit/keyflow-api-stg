<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeaveBalance extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'year',
        'annual_quota',
        'sick_quota',
        'emergency_quota',
        'unpaid_quota',
        'wfh_quota',
        'compensatory_quota',
        'annual_used',
        'sick_used',
        'emergency_used',
        'unpaid_used',
        'wfh_used',
        'compensatory_used',
    ];

    protected $casts = [
        'annual_quota' => 'float',
        'sick_quota' => 'float',
        'emergency_quota' => 'float',
        'unpaid_quota' => 'float',
        'wfh_quota' => 'float',
        'compensatory_quota' => 'float',
        'annual_used' => 'float',
        'sick_used' => 'float',
        'emergency_used' => 'float',
        'unpaid_used' => 'float',
        'wfh_used' => 'float',
        'compensatory_used' => 'float',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public static function getOrCreateForUser($userId, $year = null)
    {
        $year = $year ?? date('Y');

        return static::firstOrCreate(
            ['user_id' => $userId, 'year' => $year],
            [
                'annual_quota' => 21,
                'sick_quota' => 10,
                'emergency_quota' => 5,
                'unpaid_quota' => 30,
                'wfh_quota' => 12,
                'compensatory_quota' => 5,
                'annual_used' => 0,
                'sick_used' => 0,
                'emergency_used' => 0,
                'unpaid_used' => 0,
                'wfh_used' => 0,
                'compensatory_used' => 0,
            ]
        );
    }

    public function deductLeave(string $leaveType, float $days)
    {
        $field = strtolower($leaveType) . '_used';
        if (in_array($field, [
            'annual_used', 'sick_used', 'emergency_used',
            'unpaid_used', 'wfh_used', 'compensatory_used'
        ])) {
            $this->increment($field, $days);
        }
    }
}

