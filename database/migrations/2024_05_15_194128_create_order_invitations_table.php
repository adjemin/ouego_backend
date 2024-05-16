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
        Schema::create('order_invitations', function (Blueprint $table) {
            $table->id('id');
            $table->bigInteger('driver_id')->nullable();
            $table->bigInteger('order_id')->nullable();
            $table->boolean('is_waiting_acceptation')->nullable()->default(true);
            $table->timestamp('acceptation_time')->nullable();
            $table->timestamp('rejection_time')->nullable();
            $table->double('latitude')->nullable();
            $table->double('longitude')->nullable();
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
        Schema::drop('order_invitations');
    }
};
