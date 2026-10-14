<?php

namespace App\Models;

use App\Models\Concerns\SerializationScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{

    use SoftDeletes;

    public $table = 'products';

    protected $appends = ['product_types'];

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


}
