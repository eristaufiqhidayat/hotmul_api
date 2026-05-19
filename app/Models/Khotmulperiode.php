<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Khotmulperiode extends Model
{
    use HasFactory;
	public $timestamps = false;
    protected $table = 'khotmul_periode';
    protected $primaryKey = 'id';
    protected $fillable = ['id','periode','group_id','tanggal','keterangan'];

}
