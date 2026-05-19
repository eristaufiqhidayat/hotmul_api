<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Donasi extends Model
{
    use HasFactory;

	public $timestamps = false;
    protected $table = 'donasi';
    protected $primaryKey = 'id';
    protected $fillable = ['id','user_id','tanggal','rp']; // field yang bisa diisi mass assignment
}
