<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationDeliveryStatus extends Model
{
    protected $fillable = [
        'notification_id',
        'fcm_token',
        'fcm_message_id',
        'attempt_count',
        'status',
        'error_message',
        'delivered_at'
    ];

    protected $casts = [
        'delivered_at' => 'datetime'
    ];
}
