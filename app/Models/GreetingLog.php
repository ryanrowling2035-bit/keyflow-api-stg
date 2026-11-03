<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GreetingLog extends Model
{
    use HasFactory;

    protected $table = 'greeting_logs';

    protected $fillable = [
        'template_id',
        'template_name',
        'type',
        'contact_id',
        'employee_id',
        'recipient_name',
        'recipient_email',
        'status',
        'compiled_subject',
        'compiled_body',
        'error_message',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function template()
    {
        return $this->belongsTo(GreetingTemplate::class, 'template_id');
    }

    public function employee()
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function contact()
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }
}
