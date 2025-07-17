<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Zone extends Model
{

    use SoftDeletes;

    public $table = 'zones';

    public $fillable = [
        'name',
        'description',
        'zone_base_id',
        'geometry',
    ];

    protected $casts = [
        'name' => 'string',
        'description' => 'string',
        'zone_base_id'=> 'integer',
        'geometry' => 'string'
    ];

    public static array $rules = [

    ];

    public function getModelsAttribute(){

        return TypeEnginModel::where('type_engin_slug', $this->slug)->get();

    }



}
