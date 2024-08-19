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
        'currency_code',

        'ride_base_pricing',
        'slice_1_max_distance',
        'slice_1_pricing',
        'slice_2_max_distance',
        'slice_2_pricing',
        'manutention_pricing'
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
        'currency_code'  => 'string',

        'ride_base_pricing' => 'integer',
        'slice_1_max_distance' => 'double',
        'slice_1_pricing' => 'integer',
        'slice_2_max_distance' => 'double',
        'slice_2_pricing' => 'integer',
        'slice_3_pricing' => 'integer',
        'manutention_pricing'=> 'integer'

    ];

    public static array $rules = [

    ];


}
