<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    public $table = 'services';

    public $fillable = [
        'name',
        'image',
        'description',
        'is_active',
        'slug'
    ];

    protected $casts = [
        'name' => 'string',
        'image' => 'string',
        'description' => 'string',
        'is_active' => 'boolean',
        'slug' => 'string'
    ];

    public static array $rules = [
        
    ];

    
}
