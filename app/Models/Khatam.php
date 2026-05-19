<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Khatam extends Model
{
    use HasFactory;

	public $timestamps = false;
    protected $table = 'khatam';
    protected $primaryKey = 'id';
    protected $fillable = ['id','user_id','jumlah_khatam','jumlah_tidak_baca','jumlah_membadalkan','keterangan','tanggal']; // field yang bisa diisi mass assignment
}
