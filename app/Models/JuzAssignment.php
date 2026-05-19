<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
class JuzAssignment extends Model
{
    protected $fillable = [
        'group_id',
        'user_id',
        'juz_number',
        'start_date',
        'end_date',
        'period_index',
        'status'
    ];
}