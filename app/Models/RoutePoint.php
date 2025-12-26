<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RoutePoint extends Model
{
    use SoftDeletes;

    public $table = 'route_points';

    const  WAITING = "waiting";
    const  ACCEPTED = "accepted";
    const  STARTED = "started";
    const  ARRIVED = "arrived";
    const  FAILED = "failed";
    const  SUCCESS = "success";
    const  CANCELLED = "cancelled";


    const POINT_TYPE_SOURCE = 'source';
    const POINT_TYPE_DESTINATION = 'destination';

    protected  $appends = ['histories'];

    public $fillable = [
        'address_name',
        'latitude',
        'longitude',
        'contact_fullname',
        'contact_phone',
        'contact_second_phone',
        'contact_email',
        'parcel_details',
        'type',
        'images',
        'signatures',
        'signatures_at',
        'status',
        'delivery_fees',
        'currency_code',
        'is_arrived',
        'is_started',
        'is_waiting',
        'is_completed',
        'is_successful',
        'has_cash_management',
        'has_cash_deposited',
        'is_driver_paid',
        'completion_time',
        'expected_arrival_at',
        'visit_order',
        'stage',
        'apartment',
        'customer_id',
        'order_id',
        'has_handling'
    ];

    protected $casts = [
        'address_name' => 'string',
        'latitude' => 'double',
        'longitude' => 'double',
        'contact_fullname' => 'string',
        'contact_phone' => 'string',
        'contact_second_phone' => 'string',
        'contact_email' => 'string',
        'parcel_details' => 'string',
        'type' => 'string', //source or destination, arret
        'images' => 'array',
        'signatures' => 'string',
        'status' => 'string',
        'delivery_fees' => 'double',
        'currency_code' => 'string',
        'is_waiting' => 'boolean',
        'is_completed' => 'boolean',
        'is_successful' => 'boolean',
        'is_arrived' => 'boolean',
        'is_started' => 'boolean',
        'has_cash_management' => 'boolean',
        'has_cash_deposited' => 'boolean',
        'is_driver_paid' => 'boolean',
        'visit_order' => 'integer',
        'stage' => 'string',
        'apartment' => 'string',
        'customer_id' => 'integer',
        'order_id' => 'integer',
        'has_handling' => 'boolean'
    ];

    public static array $rules = [

    ];

    public function title(){
        return  $this->type == "source"? "Ramassage":"Livraison";
    }

    public function getHistoriesAttribute(){
        return RoutePointHistory::where(['route_point_id' => $this->id])->get();
    }


}
