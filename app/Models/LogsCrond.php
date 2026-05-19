<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LogsCrond extends Model
{
    use HasFactory;
  public $timestamps = false; 
  protected $table = 'logs_crond';
  protected $primaryKey = 'id';
    protected $fillable = ['id', 'date','message'];
}

