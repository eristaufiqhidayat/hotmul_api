<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Khotmul extends Model
{
    use HasFactory;
	public $timestamps = false;
    protected $table = 'khotmul';
    protected $primaryKey = 'id';
    protected $fillable = ['id','group_id','juz','periode','tanggal','anggota_id','status'];
}
