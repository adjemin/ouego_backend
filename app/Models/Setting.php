<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Setting extends Model
{
    public $table = 'settings';

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

        /*** HELPERS ***/
    // Get key
    public static function get($key)
    {
        $value = null;
        $setting = Setting::where('name', $key)->first();
        if ($setting) {
            $value = $setting->value;
        }
        return $value;
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
