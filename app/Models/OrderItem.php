<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    public $table = 'order_items';

    public $fillable = [
        'order_id',
        'service_slug',
        'meta_data',
        'quantity',
        'quantity_unity',
        'unit_price',
        'total_amount',
        'currency'
    ];

    protected $casts = [
        'order_id' => 'integer',
        'service_slug' => 'string',
        'meta_data' => 'string',
        'quantity' => 'integer',
        'quantity_unity' => 'string',
        'unit_price' => 'double',
        'total_amount' => 'double',
        'currency' => 'string'
    ];

    public static array $rules = [
        
    ];

    
}
