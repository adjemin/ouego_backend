<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    public $table = 'transactions';

    const CREATED = "CREATED";
    const PENDING = "PENDING";
    const SUCCESSFUL = "SUCCESSFUL";
    const FAILED = "FAILED";

    const TYPE_DEPOSIT = "deposit";
    const TYPE_CHARGE = "charge";
    const TYPE_WITHDRAWAL = "withdrawal";

    public $fillable = [
        'user_id',
        'user_source',
        'type',
        'currency_code',
        'amount',
        'is_in',
        'order_id'
    ];

    protected $casts = [
        'user_id' => 'integer',
        'user_source' => 'string',
        'type' => 'string',
        'currency_code' => 'string',
        'amount' => 'double',
        'is_in' => 'boolean',
        'order_id' => 'integer'
    ];

    public static array $rules = [

    ];


}
