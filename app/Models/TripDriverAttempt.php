<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TripDriverAttempt extends Model
{
    use HasFactory;

    public $table = 'trip_driver_attempts';

    const PENDING = "pending";
    const NOTIFIED = "notified";
    const ACCEPTED = "accepted";
    const REJECTED = "rejected";
    const TIMEOUT = "timeout";


    public $fillable = [
        'trip_request_id',
        'driver_id',
        'order_index', // ordre dans la file
        'attempt_number',
        'status', // notified, accepted, rejected, timeout
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'id' => 'integer',
        'trip_request_id' => 'integer',
        'driver_id' => 'integer',
        'order_index' => 'integer',
        'attempt_number' => 'integer',
        'status' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    public static array $rules = [

    ];

    public function tripRequest()
    {
        return $this->belongsTo(TripRequest::class, 'trip_request_id');
    }
    public function driver()
    {
        return $this->belongsTo(Driver::class, 'driver_id');
    }

    
}
