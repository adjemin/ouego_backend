<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DeliveryType extends Model
{
    use SoftDeletes;

    public $table = 'delivery_types';

    const TYPE_EXPRESS = "EXPRESS";
    const TYPE_EN_JOURNEE = "en-journee";
    const TYPE_DE_NUIT = "de-nuit";
    const TYPE_DE_SEMAINE = "en-semaine";

    public $fillable = [
        'name',
        'icon',
        'slug',
        'is_active'
    ];

    protected $casts = [
        'name' => 'string',
        'icon' => 'string',
        'slug' => 'string',
        'is_active' => 'boolean'
    ];

    public static array $rules = [

    ];


}
