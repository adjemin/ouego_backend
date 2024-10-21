<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DriverOtp extends Model
{
    public $table = 'driver_otps';

    public $fillable = [
        'otp',
        'otp_expires_at',
        'phone',
        'is_test_mode'
    ];

    protected $casts = [
        'otp' => 'string',
        'phone' => 'string',
        'is_test_mode' => 'boolean'
    ];

    public static array $rules = [
        'otp' => 'required'
    ];

    
}
