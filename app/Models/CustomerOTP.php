<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerOTP extends Model
{
    use SoftDeletes;


    public $table = 'customer_o_t_ps';

    public $fillable = [
        'otp',
        'otp_expires_at',
        'phone',
        'is_test_mode'
    ];

    protected $casts = [
        'otp' => 'string',
        'phone' => 'string',
        'is_test_mode' => 'boolean'
    ];

    public static array $rules = [

    ];


}
