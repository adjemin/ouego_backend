<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrderInvitation extends Model
{
    use SoftDeletes;

    const PENDING = "pending";
    const NOTIFIED = "notified";
    const ACCEPTED = "accepted";
    const REJECTED = "rejected";
    const TIMEOUT = "timeout";


    public $table = 'order_invitations';

    protected $appends = ['order'];

    public $fillable = [
        'driver_id',
        'order_id',
        'is_waiting_acceptation',
        'acceptation_time',
        'rejection_time',
        'latitude',
        'longitude',
        'trip_request_id',
        'index',
        'attempt_number',
        'status'
    ];

    protected $casts = [
        'driver_id' => 'integer',
        'order_id' => 'integer',
        'is_waiting_acceptation'=> 'boolean',
        'latitude' => 'double',
        'longitude' => 'double'
    ];

    public static array $rules = [

    ];

    public function getOrderAttribute(){
        return Order::where('id', $this->order_id)->first();
    }



}
