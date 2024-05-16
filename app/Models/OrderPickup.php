<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderPickup extends Model
{
    public $table = 'order_pickups';

    public $fillable = [
        'order_id',
        'location_name',
        'location_latitude',
        'location_longitude'
    ];

    protected $casts = [
        'order_id' => 'integer',
        'location_name' => 'string',
        'location_latitude' => 'double',
        'location_longitude' => 'double'
    ];

    public static array $rules = [
        
    ];

    
}
