<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\DB;

class Driver extends Authenticatable  implements JWTSubject
{

    use SoftDeletes;

    public $table = 'drivers';

    protected $guard = 'api-drivers';

    protected $appends = ['cars', 'current_order_count', 'zone_base_info'];

    public $fillable = [
        'first_name',
        'last_name',
        'name',
        'dialing_code',
        'phone_number',
        'phone',
        'photo_url',
        'is_active',
        'current_balance',
        'old_balance',
        'is_available',
        'last_location_latitude',
        'last_location_longitude',
        'services',
        'driver_license_docs',
        'rate',
        'zone_base_id',
    ];

    protected $casts = [
        'first_name' => 'string',
        'last_name' => 'string',
        'name' => 'string',
        'dialing_code' => 'string',
        'phone_number' => 'string',
        'phone' => 'string',
        'photo_url' => 'string',
        'is_active' => 'boolean',
        'is_blocked' => 'boolean',
        'is_available' => 'boolean',
        'current_balance' => 'double',
        'old_balance' => 'double',
        'last_location_latitude' => 'double',
        'last_location_longitude' => 'double',
        'driver_license_docs' => 'array',
        'services' => 'array',
        'rate' => 'string',
        'zone_base_id' => 'integer',
    ];

    public static array $rules = [

    ];



    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     *
     * @return mixed
     */
    public function getJWTIdentifier()
    {
       return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [];
    }

    /**
     * Relation many-to-many avec Carrier
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function carriers()
    {
        return $this->belongsToMany(Carrier::class, 'driver_carriers', 'driver_id', 'carrier_id');
    }

    public function getCarsAttribute(){
        return Engin::where('driver_id', $this->id)->get();
    }

    public function getServicesAttribute($value){
        if($value == null){
            return [];
        }

        $json_array =  json_decode(stripslashes($value), true);
        return $json_array ;
    }

    public function getDriverLicenseDocsAttribute($value){
        if($value == null){
            return [];
        }
        $json_array =  json_decode(stripslashes($value), true);
        return $json_array ;
    }
    public function getCurrentOrderCountAttribute(){
        return Order::where('driver_id', $this->id)
            // ->where('is_draft', false)
            ->where('is_completed', false)
            ->count();
    }

    public function setLastLocationAttribute($value){
        $latitude = $value['latitude'] ?? $this->last_location_latitude;
        $longitude = $value['longitude'] ?? $this->last_location_longitude;
        if ($latitude !== null && $longitude !== null) {
            $this->attributes['last_location'] = DB::raw("ST_SetSRID(ST_MakePoint($longitude, $latitude), 4326)::geography");
        }else{
            $this->attributes['last_location'] = null;
        }
    }

    //Credit
    public function creditBalance($amount, $orderId=null){
        $this->old_balance = $this->current_balance;
        $this->current_balance = $this->current_balance + $amount;
        $this->save();

        //Create Transaction
        Transaction::create([
            'user_id' => $this->id,
            'user_source' => $this->getTable(),
            'type' => 'credit',
            'currency_code' => 'XOF',
            'amount' => $amount,
            'is_in' => true,
            'order_id' => $orderId
        ]);
    }


    public function debitBalance($amount, $orderId=null){
        $this->old_balance = $this->current_balance;
        $this->current_balance = $this->current_balance - $amount;
        $this->save();

        //Create Transaction
        Transaction::create([
            'user_id' => $this->id,
            'user_source' => $this->getTable(),
            'type' => 'credit',
            'currency_code' => 'XOF',
            'amount' => $amount,
            'is_in' => true,
            'order_id' => $orderId
        ]);
    }

    public function hasCurrentOrders(){
        return Order::where('driver_id', $this->id)
            ->where('is_draft', false)
            ->where('is_completed', false)
            ->where('is_started', false)
            ->count();
    }

    public function ratingNote(){
        return Order::where('driver_id', $this->id)
            ->where('is_completed', true)
            ->avg('rating');
    }


    public function zoneBase()
    {
        return $this->belongsTo(Zone::class, 'zone_base_id', 'id');
    }

    public function getZoneBaseInfoAttribute()
    {
        if (!$this->zoneBase) {
            return null;
        }

        $zone = $this->zoneBase;
        $carriers = $zone->carriers()->select(['carriers.id', 'carriers.name', 'carriers.location_latitude', 'carriers.location_longitude', 'carriers.phone'])->get();
        
        return [
            'id' => $zone->id,
            'name' => $zone->name,
            'description' => $zone->description,
            'carriers' => $carriers
        ];
    }

}
