<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class OrderHistory extends Model
{
    use SoftDeletes;

    public $table = 'order_histories';

    protected $appends = ['creator_info'];

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

    public function order(){
        return $this->belongsTo(Order::class, 'order_id', 'id');
    }

    public function getCreatorInfoAttribute()
    {
        return DB::table($this->creator)
            ->where('id', $this->created_id)
            ->select('id', 'name', 'phone', 'photo_url')
            ->first();
    }



}
