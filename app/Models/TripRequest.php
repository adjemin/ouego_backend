<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TripRequest extends Model
{
    use HasFactory;

    public $table = 'trip_requests';

    const PENDING = "pending";
    const ACCEPTED = "accepted";
    const FAILED = "failed";
    const SCHEDULED = "scheduled";



    public $fillable = [
        'carrier_id',
        'order_id',
        'status',
        'scheduled_at'
    ];

    protected $casts = [
        'id' => 'integer',
        'carrier_id' => 'integer',
        'order_id' => 'integer',
        'status' => 'string',
        'scheduled_at' => 'datetime', 
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    public static array $rules = [

    ];

    public function attempts()
    {
        return $this->hasMany(TripDriverAttempt::class, 'trip_request_id', 'id');
    }

    public function invitations()
    {
        return $this->hasMany(OrderInvitation::class, 'trip_request_id', 'id');
    }
    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
    public function carrier()
    {
        return $this->belongsTo(Carrier::class, 'carrier_id');
    }

}
