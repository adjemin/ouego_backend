<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderInvitation extends Model
{
    public $table = 'order_invitations';

    public $fillable = [
        'driver_id',
        'order_id',
        'is_waiting_acceptation',
        'acceptation_time',
        'rejection_time',
        'latitude',
        'longitude'
    ];

    protected $casts = [
        'driver_id' => 'integer',
        'order_id' => 'integer',
        'latitude' => 'double',
        'longitude' => 'double'
    ];

    public static array $rules = [
        
    ];

    
}
