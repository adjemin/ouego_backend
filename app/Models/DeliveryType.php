<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryType extends Model
{
    public $table = 'delivery_types';

    public $fillable = [
        'name',
        'icon',
        'slug',
        'is_active'
    ];

    protected $casts = [
        'name' => 'string',
        'icon' => 'string',
        'slug' => 'string',
        'is_active' => 'boolean'
    ];

    public static array $rules = [
        
    ];

    
}
