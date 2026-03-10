<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    protected $table = 'attendance';

    protected $fillable = [
        'user_id',
        'check_in',
        'check_out',
        'latitude_in',
        'longitude_in',
        'latitude_out',
        'longitude_out',
        'distance_in',
        'distance_out',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
