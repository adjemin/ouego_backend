<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Carrier extends Model
{
    use SoftDeletes;

    protected static $kilometers = true;

    const LATITUDE  = 'location_latitude';
    const LONGITUDE = 'location_longitude';

    public $table = 'carriers';

    public $fillable = [
        'name',
        'phone',
        'location_latitude',
        'location_longitude',
        'is_active',
        'products',
        'location'
    ];

    protected $casts = [
        'name' => 'string',
        'phone' => 'string',
        'location_latitude' => 'double',
        'location_longitude' => 'double',
        'is_active' => 'boolean',
        'products' => 'array'
    ];

    public static array $rules = [

    ];


    public function setLocationAttribute($value)
    {
        $latitude = $value['latitude'] ?? $this->location_latitude;
        $longitude = $value['longitude'] ?? $this->location_longitude;
        if ($latitude !== null && $longitude !== null) {
            $this->attributes['location'] = DB::raw("ST_SetSRID(ST_MakePoint($longitude, $latitude), 4326)::geography");
        } else {
            $this->attributes['location'] = null;
        }
    }

    /**
     * Récupère la localisation du transporteur.
     *
     * @param mixed $value
     * @return array|null
     */
    public function getLocationAttribute($value)
    {
        if ($value) {
            $point = DB::select("SELECT ST_AsText(?) as point", [$value])[0]->point;
            preg_match('/POINT\((.*?) (.*?)\)/', $point, $matches);
            return [
                'longitude' => $matches[1],
                'latitude' => $matches[2]
            ];
        }
        return null;
    }

    /**
     * Relation many-to-many avec Driver
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function drivers()
    {
        return $this->belongsToMany(Driver::class, 'driver_carriers', 'carrier_id', 'driver_id');
    }


}
