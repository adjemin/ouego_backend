<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrderInvitation extends Model
{
    use SoftDeletes;

    public $table = 'order_invitations';

    protected $appends = ['order'];

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

    public function getOrderAttribute(){
        return Order::where('id', $this->order_id)->first();
    }



}
