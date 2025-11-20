<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;

class OrderItem extends Model
{
    use SoftDeletes;

    public $table = 'order_items';

    protected $appends = ['service','route_points','carrier'];

    public $fillable = [
        'order_id',
        'service_slug',
        'meta_data',
        'quantity',
        'quantity_unity',
        'unit_price',
        'order_price',
        'delivery_price',
        'manutention_pricing',
        'total_amount',
        'currency',
        'service_due',
        'driver_due',
        'location_start_date',
        'location_end_date',
        'carrier_id'
    ];

    protected $casts = [
        'order_id' => 'integer',
        'service_slug' => 'string',
        'meta_data' => "array",
        'quantity' => 'integer',
        'quantity_unity' => 'string',
        'unit_price' => 'double',
        'order_price'=> 'double',
        'manutention_pricing' => 'double',
        'delivery_price'=> 'double',
        'total_amount' => 'double',
        'currency' => 'string',
        'service_due' => 'double',
        'driver_due' => 'double',
        'carrier_id' => 'integer'
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
            'slug' => $this->service_slug
        ])->first();
    }

    public function getMetaDataAttribute($value){

        if($value != null && is_string($value)){

            $json_array =  json_decode(stripslashes($value), true);

            if($this->service_slug ==  "course"){

                if(array_key_exists('type_engin_slug', $json_array)){
                    $json_array['type_engin'] = TypeEngin::where('slug', $json_array['type_engin_slug'])->first();
                }

                if( array_key_exists('engin_model', $json_array)){
                    $json_array['engin_model_object'] = TypeEnginModel::where('slug', $json_array['engin_model'])->first();
                }

            }

            if($this->service_slug ==  "agregats-construction"){

                if(array_key_exists('product_slug', $json_array)){
                    $json_array['product'] = Product::where('slug', $json_array['product_slug'])->first();
                }

                if( array_key_exists('product_type_slug', $json_array)){
                    $json_array['product_type'] = ProductType::where('slug', $json_array['product_type_slug'])->first();
                }
            }

            if($this->service_slug ==  "location"){

                if(array_key_exists('type_engin_slug', $json_array)){
                    $json_array['type_engin'] = TypeEngin::where('slug', $json_array['type_engin_slug'])->first();
                }

                if( array_key_exists('engin_model', $json_array)){
                    $json_array['engin_model_object'] = TypeEnginModel::where('slug', $json_array['engin_model'])->first();
                }
            }

            return $json_array;

        }else{
            return $value;
        }


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

    public function getCarrierAttribute()
    {
        if ($this->carrier_id) {
            return Carrier::where('id', $this->carrier_id)->first();
        }
        return null;
    }

    public function carrier()
    {
        return $this->belongsTo(Carrier::class, 'carrier_id', 'id');
    }

}
