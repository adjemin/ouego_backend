<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Engin extends Model
{
    public $table = 'engins';

    public $fillable = [
        'driver_id',
        'immatriculation',
        'numero_carte_grise',
        'brand',
        'serie',
        'type_engin',
        'carrosserie',
        'color',
        'nombre_essieux',
        'nombre_roues',
        'oil',
        'usages',
        'ability_tonne',
        'ptac_tonne',
        'poids_vide',
        'charge_utile',
        'puissance_fiscale',
        'cylindree',
        'date_mise_en_production',
        'date_edition',
        'nom_proprietaire'
    ];

    protected $casts = [
        'driver_id' => 'integer',
        'immatriculation' => 'string',
        'numero_carte_grise' => 'string',
        'brand' => 'string',
        'serie' => 'string',
        'type_engin' => 'string',
        'carrosserie' => 'string',
        'color' => 'string',
        'nombre_essieux' => 'string',
        'nombre_roues' => 'string',
        'oil' => 'string',
        'ability_tonne' => 'string',
        'ptac_tonne' => 'string',
        'poids_vide' => 'string',
        'charge_utile' => 'string',
        'puissance_fiscale' => 'string',
        'cylindree' => 'string',
        'date_mise_en_production' => 'date',
        'date_edition' => 'date',
        'nom_proprietaire' => 'string'
    ];

    public static array $rules = [

    ];


}
