<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TypeEngin extends Model
{

    use SoftDeletes;

    public $table = 'type_engins';

    protected $appends = ['models'];

    public $fillable = [
        'name',
        'slug',
        'services',
        'ability_tonne',
        'usage'
    ];

    protected $casts = [
        'name' => 'string',
        'slug' => 'string',
        'services'=> 'array',
        'ability_tonne' => 'array',
        'usage' => 'string'
    ];

    public static array $rules = [

    ];

    public function getModelsAttribute(){

        return TypeEnginModel::where('type_engin_slug', $this->slug)->get();

    }



}
