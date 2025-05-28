<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ZoneMapping extends Model
{
    use HasFactory;
    
    protected $table = 'zone_mapping';

    protected $fillable = [
        'zone_id',
        'carrier_id'
    ];

    public function zone()
    {
        return $this->belongsTo(Zone::class);
    }

    public function carrier()
    {
        return $this->belongsTo(Carrier::class);
    }
}
