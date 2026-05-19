<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JuzReference extends Model
{
    protected $fillable = [
        'juz_number',
        'total_ayat'
    ];
}