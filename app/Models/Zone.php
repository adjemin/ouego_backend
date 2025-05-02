<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Zone extends Model
{
    use HasFactory;

    public $table = 'zones';

    protected $fillable = [
        'nom',
        'zone_base_id',
        'geom',
    ];

    protected $casts = [
        'id' => 'integer',
        'nom' => 'string',
        'zone_base_id' => 'integer',
        'geom' => 'geometry',
    ];

    public function zoneBase()
    {
        return $this->belongsTo(self::class, 'zone_base_id');
    }
}