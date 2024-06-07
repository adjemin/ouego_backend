<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Carrier extends Model
{
    use SoftDeletes;
    public $table = 'carriers';

    public $fillable = [
        'name',
        'phone',
        'location_latitude',
        'location_longitude',
        'is_active',
        'products'
    ];

    protected $casts = [
        'name' => 'string',
        'phone' => 'string',
        'location_latitude' => 'double',
        'location_longitude' => 'double',
        'is_active' => 'boolean'
    ];

    public static array $rules = [

    ];


}
