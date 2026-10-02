<?php

namespace App\Models;

use Carbon\Carbon;
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

    // Créneaux où l'option Express est indisponible : [début inclus, fin exclue]
    const EXPRESS_UNAVAILABLE_SLOTS = [['06:00', '09:00'], ['17:00', '19:30']];
    const EXPRESS_UNAVAILABLE_MESSAGE = "L’option Course Express n'est pas disponible de 06H00 à 09H00 et de 17H00 à 19H30.";

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

    public static function isExpressAvailable(?Carbon $at = null): bool
    {
        $time = ($at ?? now())->format('H:i');

        foreach (self::EXPRESS_UNAVAILABLE_SLOTS as [$start, $end]) {
            if ($time >= $start && $time < $end) {
                return false;
            }
        }

        return true;
    }
}
