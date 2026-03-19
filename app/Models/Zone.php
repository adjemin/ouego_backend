<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Zone extends Model
{
    public $table = 'zones';

    public $fillable = [
        'name',
        'description',
        'zone_base_id',
        'geometry'
    ];

    protected $casts = [
        'name' => 'string',
        'description' => 'string',
        'zone_base_id' => 'integer',
        'geometry' => 'string'
    ];

    public static array $rules = [
        
    ];

    public $hidden = [
        'geometry',
    ];

    /**
     * Get all of the comments for the Zone
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasManyThrough
     */
    public function carriers()
    {
        return $this->hasManyThrough(Carrier::class, ZoneMapping::class, 'zone_id', 'id', 'id', 'carrier_id')->orderBy('name', 'asc');
    }

    
}
