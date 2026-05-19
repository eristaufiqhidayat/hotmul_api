<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Models\Group;

class User extends Authenticatable implements JWTSubject
{
    use HasApiTokens, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        "role",
        "group_id"
    ];

    protected $hidden = [
        'password',
    ];

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }
    public function groups()
    {
        return $this->belongsToMany(Group::class)
            ->withPivot(['order_number', 'juz_start'])
            ->withTimestamps();
    }
    // public function groups()
    // {
    //     return $this->belongsToMany(Group::class, 'group_members', 'user_id', 'group_id')
    //         ->withPivot(['order_number', 'juz_start'])
    //         ->withTimestamps();
    // }
}
