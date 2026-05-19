<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HafalanLog extends Model
{
    protected $fillable = [
        'assignment_id',
        'user_id',
        'tanggal',
        'ayat_from',
        'ayat_to',
        'keterangan',
        'status'
    ];
}
