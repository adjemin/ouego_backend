<?php

namespace App\Models;

use App\Models\Concerns\SerializationScope;
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

        return SerializationScope::remember(
            "TypeEngin.models:{$this->slug}",
            fn () => TypeEnginModel::where('type_engin_slug', $this->slug)->get()
        );

    }



}
