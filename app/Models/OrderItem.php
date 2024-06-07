<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrderItem extends Model
{
    use SoftDeletes;

    public $table = 'order_items';

    protected $appends = ['service','route_points'];

    public $fillable = [
        'order_id',
        'service_slug',
        'meta_data',
        'quantity',
        'quantity_unity',
        'unit_price',
        'total_amount',
        'currency',
        'location_start_date',
        'location_end_date'
    ];

    protected $casts = [
        'order_id' => 'integer',
        'service_slug' => 'string',
        'meta_data' => 'array',
        'quantity' => 'integer',
        'quantity_unity' => 'string',
        'unit_price' => 'double',
        'total_amount' => 'double',
        'currency' => 'string'
    ];

    public static array $rules = [

    ];

    public function getRoutePointsAttribute()
    {
        return RoutePoint::where(['order_id' => $this->order_id])->orderBy('visit_order', 'ASC')->get();
    }

    public function getServiceAttribute()
    {
        return Service::where([
            'slug' => 'service_slug'
        ])->first();
    }

    public function getSource()
    {
        return RoutePoint::where([
            'type' => 'source',
            'order_id' => $this->order_id
        ])->first();
    }

    public function getDestinations()
    {
        return RoutePoint::where([
            'type' => 'destination',
            'order_id' => $this->order_id
        ])->get();
    }


}
