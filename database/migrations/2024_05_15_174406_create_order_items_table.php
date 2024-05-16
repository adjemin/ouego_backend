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
        Schema::create('order_items', function (Blueprint $table) {
            $table->id('id');
            $table->bigInteger('order_id')->nullable();
            $table->string('service_slug')->nullable();
            $table->string('meta_data')->nullable();
            $table->integer('quantity')->nullable();
            $table->string('quantity_unity')->nullable();
            $table->double('unit_price')->nullable();
            $table->double('total_amount')->nullable();
            $table->string('currency')->nullable()->default("XOF");
            $table->date('location_start_date')->nullable();
            $table->date('location_end_date')->nullable();
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
        Schema::drop('order_items');
    }
};
