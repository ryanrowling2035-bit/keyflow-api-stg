<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class GreetingTemplate extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'greeting_templates';

    protected $fillable = [
        'name',
        'type',
        'trigger_event',
        'holiday_date',
        'send_time',
        'status',
        'target_audience',
        'subject',
        'body',
    ];

    protected $casts = [
        'holiday_date' => 'date:Y-m-d',
    ];

    public function logs()
    {
        return $this->hasMany(GreetingLog::class, 'template_id');
    }
}

