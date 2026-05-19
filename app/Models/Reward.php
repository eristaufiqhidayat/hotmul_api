<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reward extends Model
{
    use HasFactory;

    public $timestamps = false;
    protected $table = 'reward';
    protected $primaryKey = 'id';
    protected $fillable = ['id','anggota_id','keterangan','rp']; // field yang bisa diisi mass assignment
}
