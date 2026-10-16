<?php

namespace App\Models;

use App\Models\Concerns\SerializationScope;
use App\Models\Concerns\SerializesAppendedRelations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrderItem extends Model
{
    use SoftDeletes, SerializesAppendedRelations;

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
        'location_shift_type',
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
        'carrier_id' => 'integer',
        'location_shift_type' => 'string',
    ];

    public static array $rules = [

    ];

    public function serializedRelations(): array
    {
        return ['service', 'routePoints.histories', 'carrier'];
    }

    // Points de passage de la commande de l'article
    public function routePoints()
    {
        return $this->hasMany(RoutePoint::class, 'order_id', 'order_id')->orderBy('visit_order', 'ASC');
    }

    public function getRoutePointsAttribute()
    {
        return $this->serializedRelation('routePoints');
    }

    public function service()
    {
        return $this->belongsTo(Service::class, 'service_slug', 'slug');
    }

    public function getServiceAttribute()
    {
        return $this->serializedRelation('service');
    }

    /**
     * Élément du catalogue référencé dans meta_data, partagé entre les articles d'une même sérialisation.
     * Pendant la sérialisation, il est converti en tableau tout de suite : sinon json_encode le convertirait
     * après coup, hors de la sérialisation, avec une requête par article.
     */
    private static function catalogEntry(string $model, string $slug)
    {
        return SerializationScope::remember("{$model}:{$slug}", function () use ($model, $slug) {
            $entry = $model::where('slug', $slug)->first();

            return SerializationScope::active() ? $entry?->toArray() : $entry;
        });
    }

    public function getMetaDataAttribute($value){

        if($value != null && is_string($value)){

            $json_array =  json_decode(stripslashes($value), true);

            if($this->service_slug ==  "course"){

                if(array_key_exists('type_engin_slug', $json_array)){
                    $json_array['type_engin'] = self::catalogEntry(TypeEngin::class, $json_array['type_engin_slug']);
                }

                if( array_key_exists('engin_model', $json_array)){
                    $json_array['engin_model_object'] = self::catalogEntry(TypeEnginModel::class, $json_array['engin_model']);
                }

            }

            if($this->service_slug ==  "agregats-construction"){

                if(array_key_exists('product_slug', $json_array)){
                    $json_array['product'] = self::catalogEntry(Product::class, $json_array['product_slug']);
                }

                if( array_key_exists('product_type_slug', $json_array)){
                    $json_array['product_type'] = self::catalogEntry(ProductType::class, $json_array['product_type_slug']);
                }
            }

            if($this->service_slug ==  "location"){

                if(array_key_exists('type_engin_slug', $json_array)){
                    $json_array['type_engin'] = self::catalogEntry(TypeEngin::class, $json_array['type_engin_slug']);
                }

                if( array_key_exists('engin_model', $json_array)){
                    $json_array['engin_model_object'] = self::catalogEntry(TypeEnginModel::class, $json_array['engin_model']);
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
        return $this->serializedRelation('carrier');
    }

    public function carrier()
    {
        return $this->belongsTo(Carrier::class, 'carrier_id', 'id');
    }

}
