<?php

namespace App\Models;

use App\Models\Concerns\SerializationScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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

    /**
     * Produits que ce type d'engin peut transporter.
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_engin_relations', 'type_engin_id', 'product_id')
            ->wherePivotNull('deleted_at')
            ->withTimestamps();
    }

    public function getModelsAttribute(){

        return SerializationScope::remember(
            "TypeEngin.models:{$this->slug}",
            fn () => TypeEnginModel::where('type_engin_slug', $this->slug)->get()
        );

    }



}
