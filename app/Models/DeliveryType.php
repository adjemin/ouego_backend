<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

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

    // Commandes « en journée » : de 06h00 jusqu'à l'heure limite JOURNEE_CUTOFF_HOUR (exclue)
    const EN_JOURNEE_START_HOUR = 6;
    const EN_JOURNEE_DEFAULT_CUTOFF_HOUR = 12;

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

    const CACHE_KEY = 'delivery_types.by_slug';
    const CACHE_TTL = 300;

    protected static function booted(): void
    {
        static::saved(fn () => self::flushCache());
        static::deleted(fn () => self::flushCache());
        static::restored(fn () => self::flushCache());
    }

    public static function flushCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public static function findBySlug(?string $slug): ?self
    {
        $types = Cache::remember(self::CACHE_KEY, self::CACHE_TTL, fn () => self::all()->keyBy('slug'));

        return $types->get($slug);
    }

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

    // Commandes « de nuit » : passées de 07h00 à 19h30, livrées pendant la nuit
    const DE_NUIT_UNAVAILABLE_MESSAGE = "L’option Course De nuit est disponible uniquement de 07H00 à 19H30.";

    public static function isDeNuitAvailable(?Carbon $at = null): bool
    {
        $at = $at ?? now();

        return !($at->lt($at->copy()->setTime(7, 0)) || $at->gt($at->copy()->setTime(19, 30)));
    }

    // Commandes « en semaine » : passées uniquement du lundi au jeudi
    const DE_SEMAINE_UNAVAILABLE_MESSAGE = "Les courses en semaine ne peuvent être lancées que du lundi au jeudi.";

    public static function isDeSemaineAvailable(?Carbon $at = null): bool
    {
        return ($at ?? now())->dayOfWeekIso <= 4;
    }

    /**
     * Raison pour laquelle le type de livraison ne peut pas être commandé maintenant, ou null s'il est disponible.
     */
    public static function unavailabilityMessage(?string $deliveryTypeCode): ?string
    {
        return match (true) {
            $deliveryTypeCode === self::TYPE_EXPRESS && !self::isExpressAvailable() => self::EXPRESS_UNAVAILABLE_MESSAGE,
            $deliveryTypeCode === self::TYPE_EN_JOURNEE && !self::isEnJourneeAvailable() => self::enJourneeUnavailableMessage(),
            $deliveryTypeCode === self::TYPE_DE_NUIT && !self::isDeNuitAvailable() => self::DE_NUIT_UNAVAILABLE_MESSAGE,
            $deliveryTypeCode === self::TYPE_DE_SEMAINE && !self::isDeSemaineAvailable() => self::DE_SEMAINE_UNAVAILABLE_MESSAGE,
            default => null,
        };
    }

    public static function enJourneeCutoffHour(): int
    {
        $value = Setting::get('JOURNEE_CUTOFF_HOUR');

        return is_numeric($value) ? intval($value) : self::EN_JOURNEE_DEFAULT_CUTOFF_HOUR;
    }

    public static function isEnJourneeAvailable(?Carbon $at = null, ?int $cutoffHour = null): bool
    {
        $hour = ($at ?? now())->hour;

        return $hour >= self::EN_JOURNEE_START_HOUR && $hour < ($cutoffHour ?? self::enJourneeCutoffHour());
    }

    public static function enJourneeUnavailableMessage(?int $cutoffHour = null): string
    {
        $cutoffHour = $cutoffHour ?? self::enJourneeCutoffHour();

        return sprintf("Vous pouvez passer une course en journée uniquement de %02dH00 à %02dH00.", self::EN_JOURNEE_START_HOUR, $cutoffHour);
    }
}
