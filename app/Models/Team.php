<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Team extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'status',
    ];

    public function members()
    {
        return $this->hasMany(AssignTeamMember::class, 'teamId');
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'assign_team_members', 'teamId', 'userId');
    }
}
