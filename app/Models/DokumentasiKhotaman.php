<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DokumentasiKhotaman extends Model
{
    protected $table = 'dokumentasi_khotaman';

    protected $fillable = [
        'group_id',
        'period_index',
        'juz_number',
        'user_id',
        'jenis',
        'name',
        'path',
        'mime_type',
        'size',
        'tanggal',
        'deskripsi',
    ];
}
