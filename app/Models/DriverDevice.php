<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DriverDevice extends Model
{
    public $table = 'driver_devices';

    public $fillable = [
        'driver_id',
        'firebase_id'
    ];

    protected $casts = [
        'driver_id' => 'integer',
        'firebase_id' => 'string'
    ];

    public static array $rules = [
        
    ];

    
}
