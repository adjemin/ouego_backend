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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id('id');
            $table->bigInteger('order_id')->nullable();
            $table->bigInteger('customer_id')->nullable();
            $table->string('order_source')->nullable();
            $table->string('reference')->unique();
            $table->double('subtotal')->nullable()->default(0);
            $table->double('tax')->nullable()->default(0);
            $table->double('fees_delivery')->nullable();
            $table->double('total')->nullable()->default(0);
            $table->string('status')->nullable()->default("UNPAID");
            $table->boolean('is_paid_by_customer')->nullable()->default(false);
            $table->string('currency_code')->nullable()->default("XOF");
            $table->double('driver_due')->nullable()->default(0);
            $table->double('service_due')->nullable()->default(0);
            $table->double('discount')->nullable()->default(0);
            $table->string('coupon')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('invoices');
    }
};
