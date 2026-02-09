<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Commercial extends Model
{

    use SoftDeletes;

    public $table = 'commercials';

    public $fillable = [
        'first_name',
        'last_name',
        'name',
        'phone',
        'email',
        'code',
        'current_balance',
        'old_balance',
        'is_active',
    ];

    protected $casts = [
        'first_name' => 'string',
        'last_name' => 'string',
        'name' => 'string',
        'phone' => 'string',
        'email' => 'string',
        'code' => 'string',
        'current_balance' => 'double',
        'old_balance' => 'double',
        'is_active' => 'boolean',
    ];

    public static array $rules = [

    ];

    //Credit
    public function creditBalance($amount, $orderId = null)
    {
        $this->old_balance = $this->current_balance;
        $this->current_balance = $this->current_balance + $amount;
        $this->save();

        //Create Transaction
        Transaction::create([
            'user_id' => $this->id,
            'user_source' => $this->getTable(),
            'type' => 'credit',
            'currency_code' => 'XOF',
            'amount' => $amount,
            'is_in' => true,
            'order_id' => $orderId
        ]);
    }

    //Debit
    public function debitBalance($amount, $orderId = null)
    {
        $this->old_balance = $this->current_balance;
        $this->current_balance = ($this->current_balance - $amount);
        $this->save();

        //Create Transaction
        Transaction::create([
            'user_id' => $this->id,
            'user_source' => $this->getTable(),
            'type' => 'debit',
            'currency_code' => 'XOF',
            'amount' => $amount,
            'is_in' => false,
            'order_id' => $orderId
        ]);
    }
}
