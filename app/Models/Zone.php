<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Zone extends Model
{

    use SoftDeletes;

    public $table = 'zones';

    public $fillable = [
        'name',
        'description',
        'zone_base_id',
        'geometry',
    ];

    protected $casts = [
        'name' => 'string',
        'description' => 'string',
        'zone_base_id'=> 'integer',
        'geometry' => 'string',
    ];

   public static array $rules = [
        'geometry' => 'required|geometry',
    ];

    public function zoneBase()
    {
        return $this->belongsTo(Zone::class, 'zone_base_id');
    }

    public function carreers()
    {
        return $this->belongsToMany(Carreer::class, 'zone_mapping', 'zone_id', 'carrier_id');
    }



}
