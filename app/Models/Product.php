<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{

    use SoftDeletes;

    public $table = 'products';

    protected $appends = ['product_types'];

    public $fillable = [
        'name',
        'slug',
        'per',
        'pricing_title',
        'description',
        'color',
        'icon',
        'tonne_options'
    ];

    protected $casts = [
        'name' => 'string',
        'slug' => 'string',
        'per' => 'string',
        'pricing_title' => 'string',
        'description' => 'string',
        'color' => 'string',
        'icon' => 'string',
        'tonne_options' => 'array'
    ];

    public static array $rules = [

    ];

    public function getProductTypesAttribute(){
        return ProductType::where('product_id', $this->id)->get();
    }


}
