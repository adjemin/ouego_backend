<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EnginPicture extends Model
{

    use SoftDeletes;

    public $table = 'engin_pictures';

    public $fillable = [
        'engin_id',
        'url'
    ];

    protected $casts = [
        'engin_id' => 'integer',
        'url' => 'string'
    ];

    public static array $rules = [

    ];


}
