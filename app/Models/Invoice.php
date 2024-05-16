<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    public $table = 'invoices';

    public $fillable = [
        'order_id',
        'customer_id',
        'reference',
        'subtotal',
        'tax',
        'fees_delivery',
        'total',
        'status',
        'is_paid_by_customer',
        'currency_code',
        'driver_due',
        'service_due',
        'discount',
        'coupon'
    ];

    protected $casts = [
        'order_id' => 'integer',
        'customer_id' => 'integer',
        'reference' => 'string',
        'subtotal' => 'double',
        'tax' => 'double',
        'fees_delivery' => 'double',
        'total' => 'double',
        'status' => 'string',
        'is_paid_by_customer' => 'boolean',
        'currency_code' => 'string',
        'driver_due' => 'double',
        'service_due' => 'double',
        'discount' => 'double',
        'coupon' => 'string'
    ];

    public static array $rules = [
        
    ];

    
}
