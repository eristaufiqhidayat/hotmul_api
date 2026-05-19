<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Daurah extends Model
{
    use HasFactory;
    public $timestamps = false;
    protected $table = 'groups';
    protected $primaryKey = 'id';
    protected $fillable = ['id', 'name', 'periode', 'start_date']; // field yang bisa diisi mass assignment
    public function users()
    {
        return $this->hasMany(User::class, 'group_id');
    }
}
