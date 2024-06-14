<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DriverNotification extends Model
{
    public $table = 'driver_notifications';

    public $fillable = [
        'driver_id',
        'title',
        'subtitle',
        'data_id',
        'type',
        'is_read',
        'is_received',
        'meta_data'
    ];

    protected $casts = [
        'driver_id' => 'integer',
        'title' => 'string',
        'subtitle' => 'string',
        'data_id' => 'integer',
        'type' => 'string',
        'is_read' => 'boolean',
        'is_received' => 'boolean',
        'meta_data' => 'string'
    ];

    public static array $rules = [

    ];


}
