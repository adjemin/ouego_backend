<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    public $table = 'orders';

    public $fillable = [
        'customer_id',
        'driver_id',
        'services',
        'status',
        'comment',
        'order_date',
        'is_started',
        'is_running',
        'is_waiting',
        'is_completed',
        'completion_time',
        'start_time',
        'acceptation_time',
        'expected_arrival_at',
        'rating_id',
        'rating',
        'rating_note',
        'order_price',
        'currency_code',
        'payment_method_code',
        'delivery_type_code',
        'is_location',
        'is_product',
        'is_ride'
    ];

    protected $casts = [
        'customer_id' => 'integer',
        'driver_id' => 'integer',
        'status' => 'string',
        'comment' => 'string',
        'is_started' => 'boolean',
        'is_running' => 'boolean',
        'is_waiting' => 'boolean',
        'is_completed' => 'boolean',
        'rating_id' => 'integer',
        'rating' => 'integer',
        'rating_note' => 'string',
        'order_price' => 'double',
        'currency_code' => 'string',
        'payment_method_code' => 'string',
        'delivery_type_code' => 'string',
        'is_location' => 'boolean',
        'is_product' => 'boolean',
        'is_ride' => 'boolean'
    ];

    public static array $rules = [
        
    ];

    
}
