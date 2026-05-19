<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Anggota extends Model
{
    use HasFactory;
  public $timestamps = false; 
  protected $table = 'daurah_anggota';
  protected $primaryKey = 'user_id';
    protected $fillable = ['name', 'group_id','user_id'];
}

