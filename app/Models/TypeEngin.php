<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TypeEngin extends Model
{
    public $table = 'type_engins';

    public $fillable = [
        'ability',
        'usages',
        'name',
        'slug',
        'models',
        'services'
    ];

    protected $casts = [
        'ability' => 'string',
        'usages' => 'string',
        'name' => 'string',
        'slug' => 'string'
    ];

    public static array $rules = [
        'services' => 'exit'
    ];

    
}
