<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    public $table = 'products';

    public $fillable = [
        'name',
        'slug',
        'price',
        'per',
        'pricing_title',
        'description',
        'color',
        'icon',
        'product_types',
        'currency_code',
        'tonne_options'
    ];

    protected $casts = [
        'name' => 'string',
        'slug' => 'string',
        'price' => 'double',
        'per' => 'string',
        'pricing_title' => 'string',
        'description' => 'string',
        'color' => 'string',
        'icon' => 'string',
        'currency_code' => 'string'
    ];

    public static array $rules = [
        
    ];

    
}
