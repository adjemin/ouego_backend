<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerNotification extends Model
{
    public $table = 'customer_notifications';

    public $fillable = [
        'customer_id',
        'title',
        'subtitle',
        'data_id',
        'type',
        'is_read',
        'is_received',
        'meta_data'
    ];

    protected $casts = [
        'customer_id' => 'integer',
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
