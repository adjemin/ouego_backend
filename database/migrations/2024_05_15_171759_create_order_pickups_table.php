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
        Schema::create('order_pickups', function (Blueprint $table) {
            $table->id('id');
            $table->bigInteger('order_id')->nullable();
            $table->string('location_name')->nullable();
            $table->double('location_latitude')->nullable();
            $table->double('location_longitude')->nullable();
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
        Schema::drop('order_pickups');
    }
};
