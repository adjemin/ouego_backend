<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;
use Illuminate\Foundation\Auth\User as Authenticatable;
use App\Models\Geographical;
//use Grimzy\LaravelMysqlSpatial\Eloquent\SpatialTrait;
//use Grimzy\LaravelMysqlSpatial\Types\Point;

class Driver extends Authenticatable  implements JWTSubject
{

    use SoftDeletes;
    use Geographical;

    /*use SpatialTrait;

    protected $spatialFields = [
        'last_location',
    ];*/


    protected static $kilometers = true;

    const LATITUDE  = 'last_location_latitude';
    const LONGITUDE = 'last_location_longitude';

    public $table = 'drivers';

    protected $guard = 'api-drivers';

    protected $appends = ['cars'];

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
        'driver_license_docs'
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
        'is_available' => 'boolean',
        'current_balance' => 'double',
        'old_balance' => 'double',
        'last_location_latitude' => 'double',
        'last_location_longitude' => 'double',
        'driver_license_docs' => 'array',
        'services' => 'array'
    ];

    public static array $rules = [

    ];

    public function setLastLocationAttribute($value)
    {
        $this->attributes['last_location'] = new Point($this->last_location_latitude, $this->last_location_longitude);
    }

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



}
