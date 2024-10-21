<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Slide extends Model
{
    public $table = 'slides';

    public $fillable = [
        'title',
        'description',
        'image_url',
        'is_active',
        'color'
    ];

    protected $casts = [
        'title' => 'string',
        'description' => 'string',
        'image_url' => 'string',
        'is_active' => 'boolean',
        'color' => 'string'
    ];

    public static array $rules = [
        'title' => 'required',
        'description' => 'required',
        'image_url' => 'required'
    ];

    
}
