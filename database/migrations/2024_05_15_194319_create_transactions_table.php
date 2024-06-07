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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id('id');
            $table->bigInteger('user_id')->nullable();
            $table->string('user_source')->nullable()->comment("customers or drivers");
            $table->string('type')->nullable();
            $table->string('currency_code')->nullable()->default("XOF");
            $table->double('amount')->nullable()->default(0);
            $table->boolean('is_in')->nullable()->default(false);
            $table->bigInteger('order_id')->nullable();
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
        Schema::drop('transactions');
    }
};
