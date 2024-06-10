<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Customer extends Authenticatable  implements JWTSubject
{

    use SoftDeletes;

    public $table = 'customers';

    protected $guard = 'api-customers';

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
        'last_location_latitude',
        'last_location_longitude',
        'last_location_name',
        'country_code',
        'is_phone_verified',
        'is_email_verified',
        'email'
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
        'current_balance' => 'double',
        'old_balance' => 'double',
        'last_location_latitude' => 'double',
        'last_location_longitude' => 'double',
        'last_location_name' => 'string',
        'country_code' => 'string',
        'is_phone_verified' => 'boolean',
        'is_email_verified' => 'boolean',
        'email' => 'string',
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


}
