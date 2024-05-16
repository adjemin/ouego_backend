<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductType extends Model
{
    public $table = 'product_types';

    public $fillable = [
        'product_id',
        'name',
        'slug',
        'description'
    ];

    protected $casts = [
        'product_id' => 'integer',
        'name' => 'string',
        'slug' => 'string',
        'description' => 'string'
    ];

    public static array $rules = [
        
    ];

    
}
