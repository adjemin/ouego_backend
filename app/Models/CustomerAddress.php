<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerAddress extends Model
{
    public $table = 'customer_address';

    public $fillable = [
        'customer_id',
        'address_name',
        'latitude',
        'longitude',
        'details'
    ];

    protected $casts = [
        'customer_id' => 'integer',
        'address_name' => 'string',
        'latitude' => 'double',
        'longitude' => 'double',
        'details' => 'string'
    ];

    public static array $rules = [
        
    ];

    
}
