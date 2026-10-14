<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    public $table = 'settings';

    const CACHE_KEY = 'settings.values';
    // Borne la durée d'une valeur périmée quand le cache n'est pas partagé entre conteneurs (api, worker, cron)
    const CACHE_TTL = 300;

    public $fillable = [
        'name',
        'value'
    ];

    protected $casts = [
        'name' => 'string',
        'value' => 'string'
    ];

    public static array $rules = [

    ];

    protected static function booted(): void
    {
        static::saved(fn () => self::flushCache());
        static::deleted(fn () => self::flushCache());
    }

    public static function flushCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

        /*** HELPERS ***/
    // Get key
    public static function get($key)
    {
        // Tous les paramètres en une seule requête, au lieu d'une requête par appel
        $values = Cache::remember(self::CACHE_KEY, self::CACHE_TTL, fn () => Setting::pluck('value', 'name')->all());

        return $values[$key] ?? null;
    }
    // Array data
    public static function getAll()
    {
        $data = [];
        $settings = Setting::all();
        foreach($settings as $setting) {
            $data[$setting->key] = $setting->value;
        }
        return $data;
    }


    public static function set($key, $value)
    {
        if ($key && $value) {
            $setting = self::get($key);
            if (! $setting) {
                $setting = Setting::create(['name' => $key, 'value' => $value]);
                if ($setting) {
                    return true;
                }
            }
        }
        return false;
    }

    public static function updateValue($key, $value)
    {
        if ($key && $value) {
            $setting = self::get($key);
            if (! $setting) {

                $setting = Setting::create(['name' => $key, 'value' => $value]);
                if ($setting) {
                    return true;
                }
            }
            $setting->value = $value;
            $setting->save();
        }
        return false;
    }



}
