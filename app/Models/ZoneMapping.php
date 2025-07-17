<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ZoneMapping extends Model
{


    public $table = 'zone_mapping';

    public $fillable = [
        'zone_id',
        'carrier_id',
    ];

    protected $casts = [
        'zone_id' => 'integer',
        'carrier_id' => 'integer'
    ];

    public static array $rules = [

    ];

    public function getModelsAttribute(){

        return TypeEnginModel::where('type_engin_slug', $this->slug)->get();

    }



}
