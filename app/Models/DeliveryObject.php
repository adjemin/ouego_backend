<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliveryObject extends Model
{
    use HasFactory;

    public $table = 'delivery_objects';

    public $fillable = [
        'name'
    ];

    protected $casts = [
        
    ];

    public static array $rules = [
        
    ];
}
