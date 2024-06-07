<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TypeEnginModel extends Model
{
    public $table = 'type_engin_models';

    public $fillable = [
        'type_engin_slug',
        'title',
        'group_tag',
        'subtitle',
        'slug',
        'serie',
        'carrosserie',
        'nombre_essieux',
        'nombre_roues',
        'oil',
        'ability_tonne',
        'ptac_tonne',
        'poids_vide',
        'charge_utile',
        'puissance_fiscale',
        'cylindree',
        'price',
        'currency_code'
    ];

    protected $casts = [
        'type_engin_slug' => 'string',
        'title' => 'string',
        'group_tag' => 'string',
        'title_slug' => 'string',
        'subtitle' => 'string',
        'slug' => 'string',
        'serie' => 'string',
        'carrosserie' => 'string',
        'nombre_essieux' => 'string',
        'nombre_roues' => 'string',
        'oil' => 'string',
        'ability_tonne' => 'array',
        'ptac_tonne' => 'string',
        'poids_vide' => 'string',
        'charge_utile' => 'string',
        'puissance_fiscale' => 'string',
        'cylindree' => 'string',
        'price' => 'double',
        'currency_code'  => 'string'
    ];

    public static array $rules = [

    ];


}
