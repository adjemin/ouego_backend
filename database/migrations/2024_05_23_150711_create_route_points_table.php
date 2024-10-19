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
        Schema::create('route_points', function (Blueprint $table) {
            $table->id('id');
            $table->string('address_name')->nullable();
            $table->double('latitude')->nullable();
            $table->double('longitude')->nullable();
            $table->string('contact_fullname')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('contact_second_phone')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('parcel_details')->nullable();
            $table->string('type')->nullable();
            $table->text('images')->nullable();
            $table->string('signatures')->nullable();
            $table->timestamp('signatures_at')->nullable();
            $table->string('status')->nullable();
            $table->double('delivery_fees')->nullable();
            $table->string('currency_code')->nullable()->default('XOF');
            $table->boolean('is_waiting')->nullable()->default(true);
            $table->boolean('is_completed')->nullable()->default(false);
            $table->boolean('is_successful')->nullable()->default(false);
            $table->boolean('has_cash_management')->nullable()->default(true);
            $table->boolean('has_cash_deposited')->nullable()->default(false);
            $table->boolean('is_driver_paid')->nullable()->default(false);
            $table->timestamp('completion_time')->nullable();
            $table->timestamp('expected_arrival_at')->nullable();
            $table->integer('visit_order')->nullable();
            $table->string('stage')->nullable();
            $table->string('apartment')->nullable();
            $table->bigInteger('customer_id')->nullable();
            $table->bigInteger('order_id')->nullable();
            $table->boolean('has_handling')->nullable()->default(false);
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
        Schema::drop('route_points');
    }
};
