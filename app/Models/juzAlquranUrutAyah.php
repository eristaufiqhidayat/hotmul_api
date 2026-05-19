<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class juzAlquranUrutAyah extends Model
{
    use HasFactory;

    protected $table = 'juzAlquran_urutAyah';
    protected $primaryKey = 'id';
    
    protected $fillable = [
        'juz',
        'noSurah_awal',
        'noAyah_awal',
        'noSurah_akhir',
        'noAyah_akhir',
        'globalAwal',
        'globalAkhir'
    ];

    public $timestamps = false;
}