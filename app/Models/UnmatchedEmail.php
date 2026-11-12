<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UnmatchedEmail extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'messageId',
        'senderName',
        'senderEmail',
        'subject',
        'snippet',
        'body',
        'dateReceived',
    ];

    protected $casts = [
        'dateReceived' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

