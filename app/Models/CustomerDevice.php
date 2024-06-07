<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerDevice extends Model
{
    use SoftDeletes;

    public $table = 'customer_devices';

    public $fillable = [
        'customer_id',
        'firebase_id'
    ];

    protected $casts = [
        'customer_id' => 'integer',
        'firebase_id' => 'string'
    ];

    public static array $rules = [

    ];


}
