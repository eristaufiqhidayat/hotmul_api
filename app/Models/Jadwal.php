<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Jadwal extends Model
{
    use HasFactory;

	public $timestamps = false;
    protected $table = 'jadwal_khatam';
    protected $primaryKey = 'id';
    protected $fillable = [	'id','nama_acara','nama_tempat','waktu','alamat']; // field yang bisa diisi mass assignment
}
