<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class groupUser extends Model
{
    use HasFactory;
	public $timestamps = false;
    protected $table = 'group_user';
    protected $primaryKey = 'id';
    protected $fillable = ['id','name']; // field yang bisa diisi mass assignment
}
