<?php

namespace App\Models;

use App\Models\Concerns\SerializationScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{

    use SoftDeletes;

    public $table = 'products';

    protected $appends = ['product_types', 'type_engins'];

   const GRAVIER_SLUG = "gravier";
   const SABLE_SLUG = "sable";
   const CIMENT_SLUG = "ciment";

    public $fillable = [
        'name',
        'slug',
        'per',
        'pricing_title',
        'description',
        'color',
        'icon',
        'tonne_options',
        'pricings'
    ];

    protected $casts = [
        'name' => 'string',
        'slug' => 'string',
        'per' => 'string',
        'pricing_title' => 'string',
        'description' => 'string',
        'color' => 'string',
        'icon' => 'string',
        'tonne_options' => 'array',
        'pricings' => 'array'
    ];

    public static array $rules = [

    ];

    public function getProductTypesAttribute(){
        return SerializationScope::remember(
            "Product.product_types:{$this->id}",
            fn () => ProductType::where('product_id', $this->id)->get()
        );
    }

    /**
     * Types d'engin (camions) capables de transporter ce produit.
     */
    public function typeEngins(): BelongsToMany
    {
        return $this->belongsToMany(TypeEngin::class, 'product_engin_relations', 'product_id', 'type_engin_id')
            ->wherePivotNull('deleted_at')
            ->withTimestamps();
    }

    public function getTypeEnginsAttribute()
    {
        return SerializationScope::remember(
            "Product.type_engins:{$this->id}",
            fn () => $this->typeEngins()->get()->makeHidden('pivot')
        );
    }


}
