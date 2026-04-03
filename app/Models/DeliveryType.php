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

    const OPERATOR_ADD              = "add";
    const OPERATOR_SUBTRACT         = "subtract";
    const OPERATOR_MULTIPLY         = "multiply";
    const OPERATOR_DIVIDE           = "divide";
    const OPERATOR_ADD_PERCENT      = "add_percent";
    const OPERATOR_SUBTRACT_PERCENT = "subtract_percent";

    public $fillable = [
        'name',
        'icon',
        'slug',
        'is_active',
        'pricing_operator',
        'pricing_value',
    ];

    protected $casts = [
        'name' => 'string',
        'icon' => 'string',
        'slug' => 'string',
        'is_active' => 'boolean',
        'pricing_operator' => 'string',
        'pricing_value' => 'float',
    ];

    public static array $rules = [

    ];


}
