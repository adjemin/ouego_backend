<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{

    use SoftDeletes;

    const UNPAID = "UNPAID";
    const PAID = "PAID";

    public $appends = ['payments'];

    public $table = 'invoices';

    public $fillable = [
        'order_id',
        'customer_id',
        'order_source',
        'reference',
        'subtotal',
        'tax',
        'fees_delivery',
        'fees_manutention',
        'total',
        'status',
        'is_paid_by_customer',
        'currency_code',
        'driver_due',
        'service_due',
        'discount',
        'coupon'
    ];

    protected $casts = [
        'order_id' => 'integer',
        'customer_id' => 'integer',
        'order_source' => 'string',
        'reference' => 'string',
        'subtotal' => 'double',
        'tax' => 'double',
        'fees_delivery' => 'double',
        'fees_manutention', 'double',
        'total' => 'double',
        'status' => 'string',
        'is_paid_by_customer' => 'boolean',
        'currency_code' => 'string',
        'driver_due' => 'double',
        'service_due' => 'double',
        'discount' => 'double',
        'coupon' => 'string'
    ];

    public static array $rules = [

    ];


     /**
     * Generate ID
     * @return int|string
     */
    public static function generateID($service, $orderId, $customerId){
        //get last record
        $record = Invoice::count() + 1;

        return $service.'-'.$record.'-'.$orderId.'-'.$customerId.'-'.time();
    }

    public function getPaymentsAttribute(){
        return Payment::where('invoice_id', $this->id)->get();
    }

}
