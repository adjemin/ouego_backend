<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderDelivery extends Model
{
    public $table = 'order_deliveries';

    public $fillable = [
        'order_id',
        'location_name',
        'location_latitude',
        'location_longitude',
        'comment'
    ];

    protected $casts = [
        'order_id' => 'integer',
        'location_name' => 'string',
        'location_latitude' => 'double',
        'location_longitude' => 'double',
        'comment' => 'string'
    ];

    public static array $rules = [
        
    ];

    
}
