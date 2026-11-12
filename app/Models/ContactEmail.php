<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContactEmail extends Model
{
    use HasFactory;

    protected $fillable = [
        'contact_id',
        'user_id',
        'message_id',
        'conversation_id',
        'sender_name',
        'sender_email',
        'subject',
        'snippet',
        'body',
        'is_incoming',
        'received_at',
    ];

    protected $casts = [
        'is_incoming' => 'boolean',
        'received_at' => 'datetime',
    ];

    public function contact()
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

