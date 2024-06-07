<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Service extends Model
{

    use SoftDeletes;

    public $table = 'services';

    const COURSE = "course";
    const AGREGATS_CONSTRUCTION = "agregats-construction";
    const LOCATION = "location";

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
