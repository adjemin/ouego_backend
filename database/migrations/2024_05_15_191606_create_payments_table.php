<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id('id');
            $table->bigInteger('invoice_id')->nullable();
            $table->string('payment_method_code')->nullable();
            $table->string('payment_reference')->nullable();
            $table->double('amount')->nullable()->default(0);
            $table->string('currency_code')->nullable()->default("XOF");
            $table->bigInteger('user_id')->nullable();
            $table->string('status')->nullable();
            $table->boolean('is_waiting')->nullable()->default(true);
            $table->boolean('is_completed')->nullable()->default(false);
            $table->string('payment_gateway_trans_id')->nullable();
            $table->string('payment_gateway_custom')->nullable();
            $table->string('payment_gateway_currency')->nullable();
            $table->string('payment_gateway_amount')->nullable();
            $table->string('payment_gateway_payment_date')->nullable();
            $table->string('payment_gateway_error_message')->nullable();
            $table->string('payment_gateway_payment_method')->nullable();
            $table->string('payment_gateway_buyer_name')->nullable();
            $table->string('payment_gateway_buyer_reference')->nullable();
            $table->string('payment_gateway_trans_status')->nullable();
            $table->string('payment_gateway_designation')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('payments');
    }
};
