<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductType extends Model
{

    use SoftDeletes;

    public $table = 'product_types';

    public $fillable = [
        'product_id',
        'name',
        'slug',
        'description',
        'price',
        'currency_code'
    ];

    protected $casts = [
        'product_id' => 'integer',
        'name' => 'string',
        'slug' => 'string',
        'description' => 'string',
        'price' => 'double',
        'currency_code' => 'string'
    ];

    public static array $rules = [

    ];


}
