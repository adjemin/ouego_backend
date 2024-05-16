<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductEnginRelation extends Model
{
    public $table = 'product_engin_relations';

    public $fillable = [
        'product_id',
        'type_engin_id'
    ];

    protected $casts = [
        'product_id' => 'integer',
        'type_engin_id' => 'integer'
    ];

    public static array $rules = [
        
    ];

    
}
