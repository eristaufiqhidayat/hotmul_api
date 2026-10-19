<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KasTransaksi extends Model
{
    protected $table = 'kas_transaksi';

    protected $fillable = [
        'group_id',
        'user_id',
        'jenis',
        'tanggal',
        'nominal',
        'metode',
        'keterangan',
        'bukti_path',
        'is_verified',
        'verified_by',
        'verified_at',
    ];

    protected $casts = [
        'is_verified' => 'boolean',
        'verified_at' => 'datetime',
    ];
}
