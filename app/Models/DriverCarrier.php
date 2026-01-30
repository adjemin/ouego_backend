<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DriverCarrier extends Model
{
    public $table = 'driver_carriers';

    public $fillable = [
        'driver_id',
        'carrier_id'
    ];

    protected $casts = [
        'driver_id' => 'integer',
        'carrier_id' => 'integer'
    ];

    public static array $rules = [
        
    ];

    public function driver()
    {
        return $this->belongsTo(Driver::class, 'driver_id');
    }
    public function carrier()
    {
        return $this->belongsTo(Carrier::class, 'carrier_id');
    }

    
}
