<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class orderHistory extends Model
{
    use SoftDeletes;

    public $table = 'order_histories';

    protected $appends = ['order'];

    public $fillable = [
        'order_id',
        'status',
        'creator',
        'created_id',
    ];

    protected $casts = [
        'order_id' => 'integer',
        'status'=> 'string',
        'creator' => 'string',
        'created_id' => 'integer'
    ];

    public static array $rules = [

    ];

    public function getOrderAttribute(){
        return Order::where('id', $this->order_id)->first();
    }



}
