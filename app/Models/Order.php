<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Order extends Model
{
    use SoftDeletes;

    public $table = 'orders';

    //STATUS
    const INITIATED = "initiated";
    const NEW = "new";
    const ESTIMATING = "estimating";
    //estimating_failed
    const ESTIMATING_FAILED = "estimating_failed";
    //ready_for_approval
    const READY_FOR_APPROVAL = "ready_for_approval";
    //failed
    const FAILED = "failed";
    //accepted A claim must be confirmed within n 10 minutes, otherwise it’ll get t «failed» status
    const ACCEPTED = "accepted";
    //performer_lookup
    const PERFORMER_LOOKUP = "performer_lookup";
    //performer_draft
    const PERFORMER_DRAFT ="performer_draft";
    //performer_not_found
    const PERFORMER_NOT_FOUND = "performer_not_found";
    //cancelled_by_taxi
    const CANCELLED_BY_TAXI = "cancelled_by_taxi";
    //performer_found
    const PERFORMER_FOUND = "performer_found";
    //pickup_arrived
    const PICKUP_ARRIVED = "pickup_arrived";
    //ready_for_pickup_confirmation
    const READY_FOR_PICKUP_CONFIRMATION = "ready_for_pickup_confirmation";
    //pickuped //Now you can cancel a claim only contacting support, editing must be done with /v2/claims/apply-changes/request request
    const PICKUPED = "pickuped";
    //delivery_arrived //Courier tries to reach out a client  within 10 minutes at least. If it’s impossible, a parcel needs to be returned
    const DELIVERY_ARRIVED = "delivery_arrived";
    //pay_waiting //This status appears only while paying upon receipt
    const PAY_WAITING = "pay_waiting";
    //ready_for_delivery_confirmation
    const READY_FOR_DELIVERY_CONFIRMATION  = "ready_for_delivery_confirmation";
    //returning
    const RETURNING = "returning";
    //return_arrived
    const RETURN_ARRIVED = "return_arrived";
    //ready_for_return_confirmation
    const READY_FOR_RETURN_CONFIRMATION  = "ready_for_return_confirmation";
    //returned_finish Both delivered and delivered_finish statuses can be considered as final
    const RETURNED_FINISH = "returned_finish";
    //delivered  //Both delivered and delivered_finish statuses can be considered as final
    const DELIVERED = "delivered";
    //delivered_finish //Both delivered and delivered_finish statuses can be considered as final
    const DELIVERED_FINISH = "delivered_finish";
    //cancelled_with_items_on_hands If you’ll specify optional_return as true, packages won’t be returned
    const CANCELLED_WITH_ITEMS_ON_HANDS = "cancelled_with_items_on_hands";
    //cancelled Final status for free cancellation
    const CANCELLED = "cancelled";
    //cancelled_with_payment //Final status for paid cancellation
    const CANCELLED_WITH_PAYMENT = "cancelled_with_payment";

    protected $appends = ['service','items', 'invoice', 'route_points'];

    public $fillable = [
        'reference',
        'customer_id',
        'driver_id',
        'service_slug',
        'status',
        'comment',
        'order_date',
        'is_started',
        'is_running',
        'is_waiting',
        'is_completed',
        'completion_time',
        'start_time',
        'acceptation_time',
        'expected_arrival_at',
        'rating_id',
        'rating',
        'rating_note',
        'order_price',
        'currency_code',
        'payment_method_code',
        'delivery_type_code',
        'delivery_price',
        'is_location',
        'is_product',
        'is_ride',
        'is_draft'
    ];

    protected $casts = [
        'reference' => 'string',
        'customer_id' => 'integer',
        'driver_id' => 'integer',
        'service_slug' => 'string',
        'status' => 'string',
        'comment' => 'string',
        'is_started' => 'boolean',
        'is_running' => 'boolean',
        'is_waiting' => 'boolean',
        'is_completed' => 'boolean',
        'rating_id' => 'integer',
        'rating' => 'integer',
        'rating_note' => 'string',
        'order_price' => 'double',
        'delivery_price'=> 'double',
        'currency_code' => 'string',
        'payment_method_code' => 'string',
        'delivery_type_code' => 'string',
        'is_location' => 'boolean',
        'is_product' => 'boolean',
        'is_ride' => 'boolean',
        'is_draft' => 'boolean'
    ];

    public static array $rules = [

    ];

    // Generate unique reference
    public static function generateReference(){

        $tries = 0;
        //$length = 8;
        do{
            /*$chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
            $ret = '';
            for($i = 0; $i < $length; ++$i) {
                $random = str_shuffle($chars);
                $ret .= $random[0];
            }*/
            $day_unique_reference = strtoupper(explode('-',Str::uuid())[0]);
            $ref = date("Y-m-d")."-".$day_unique_reference;
            $exists = Order::where(['reference'=> $ref])->first();
            $tries++;
        } while($exists && $tries < 3);

        return $ref;
    }

    public function getItemsAttribute(){
        return OrderItem::where('order_id', $this->id)->get();
    }

    public function getInvoiceAttribute(){
        return Invoice::where('order_id', $this->id)->first();
    }

    public function getRoutePointsAttribute()
    {
        return RoutePoint::where(['order_id' => $this->id])->orderBy('visit_order', 'ASC')->get();
    }

    public function getDriverAttribute()
    {

        return Driver::where(['id' => $this->driver_id])->first();
    }

    public function getSource()
    {
        return RoutePoint::where([
            'type' => 'source',
            'order_id' => $this->id
        ])->first();
    }

    public function getDestinations()
    {
        return RoutePoint::where([
            'type' => 'destination',
            'order_id' => $this->id
        ])->get();
    }


    public function getServiceAttribute()
    {
        return Service::where('slug', $this->service_slug)->first();
    }



}
