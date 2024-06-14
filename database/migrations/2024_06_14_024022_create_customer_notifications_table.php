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
        Schema::create('customer_notifications', function (Blueprint $table) {
            $table->id('id');
            $table->bigInteger('customer_id')->nullable();
            $table->string('title')->nullable();
            $table->string('subtitle')->nullable();
            $table->bigInteger('data_id')->nullable();
            $table->string('type')->nullable();
            $table->boolean('is_read')->nullable()->default(false);
            $table->boolean('is_received')->nullable()->default(false);
            $table->text('meta_data')->nullable();
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
        Schema::drop('customer_notifications');
    }
};
