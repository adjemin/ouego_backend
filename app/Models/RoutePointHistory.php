<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RoutePointHistory extends Model
{
    use SoftDeletes;

    public $table = 'route_point_histories';

    public $fillable = [
        'route_point_id',
        'latitude',
        'longitude',
        'status'
    ];

    protected $casts = [
        'route_point_id' => 'integer',
        'latitude' => 'double',
        'longitude' => 'double',
        'status' => 'string'
    ];

    public static array $rules = [

    ];


}
