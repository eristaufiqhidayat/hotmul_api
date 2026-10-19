<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserBadge extends Model
{
    public const PENGGANTI_AKTIF = 'pengganti_aktif';

    protected $fillable = [
        'user_id',
        'badge',
        'keterangan',
        'badal_id',
    ];
}
