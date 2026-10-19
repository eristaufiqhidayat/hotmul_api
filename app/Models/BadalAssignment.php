<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BadalAssignment extends Model
{
    protected $fillable = [
        'assignment_id',
        'original_user_id',
        'pengganti_user_id',
        'group_id',
        'juz_number',
        'period_index',
        'status',
        'selesai_at',
    ];

    protected $casts = [
        'selesai_at' => 'datetime',
    ];
}
