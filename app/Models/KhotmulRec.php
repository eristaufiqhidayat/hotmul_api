<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KhotmulRec extends Model
{
    use HasFactory;
	public $timestamps = false;
    protected $table = 'khotmul_rec';
    protected $primaryKey = 'id';
    protected $fillable = ['id','khotmul_id','periode_id','name','path','mime_type','size','juz','surah','ayah_awal','ayah_akhir','anggota_id','group_id','catatan'];
  

}
