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
        Schema::create('orders', function (Blueprint $table) {
            $table->id('id');
            $table->string('reference')->nullable()->unique();
            $table->bigInteger('customer_id')->nullable();
            $table->bigInteger('driver_id')->nullable();
            $table->string('service_slug')->nullable();
            $table->string('status')->nullable();
            $table->text('comment')->nullable();
            $table->timestamp('order_date')->nullable();
            $table->boolean('is_started')->nullable()->default(false);
            $table->boolean('is_running')->nullable()->default(false);
            $table->boolean('is_waiting')->nullable()->default(false);
            $table->boolean('is_completed')->nullable()->default(false);
            $table->boolean('is_successful')->nullable()->default(false);
            $table->timestamp('completion_time')->nullable();
            $table->timestamp('start_time')->nullable();
            $table->timestamp('acceptation_time')->nullable();
            $table->timestamp('expected_arrival_at')->nullable();
            $table->bigInteger('rating_id')->nullable();
            $table->integer('rating')->nullable();
            $table->string('rating_note')->nullable();
            $table->double('order_price')->nullable();
            $table->double('delivery_price')->nullable();
            $table->string('currency_code')->nullable()->default("XOF");
            $table->string('payment_method_code')->nullable()->default("cash");
            $table->string('delivery_type_code')->nullable();
            $table->boolean('is_location')->nullable()->default(false);
            $table->boolean('is_product')->nullable()->default(false);
            $table->boolean('is_ride')->nullable()->default(false);
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
        Schema::drop('orders');
    }
};
