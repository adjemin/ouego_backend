<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    public $table = 'customers';

    public $fillable = [
        'first_name',
        'last_time',
        'name',
        'dialing_code',
        'phone_number',
        'phone',
        'photo_url',
        'is_active',
        'current_balance',
        'old_balance',
        'last_location_latitude',
        'last_location_longitude',
        'last_location_name',
        'country_code',
        'is_phone_verified',
        'is_email_verified',
        'email'
    ];

    protected $casts = [
        'first_name' => 'string',
        'last_time' => 'string',
        'name' => 'string',
        'dialing_code' => 'string',
        'phone_number' => 'string',
        'phone' => 'string',
        'photo_url' => 'string',
        'is_active' => 'boolean',
        'current_balance' => 'double',
        'old_balance' => 'double',
        'last_location_latitude' => 'double',
        'last_location_longitude' => 'double',
        'last_location_name' => 'string',
        'country_code' => 'string',
        'is_phone_verified' => 'boolean',
        'is_email_verified' => 'boolean',
        'email' => 'string'
    ];

    public static array $rules = [
        
    ];

    
}
