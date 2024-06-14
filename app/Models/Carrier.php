<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Geographical;

class Carrier extends Model
{
    use SoftDeletes;
    use Geographical;


    protected static $kilometers = true;

    const LATITUDE  = 'location_latitude';
    const LONGITUDE = 'location_longitude';

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
