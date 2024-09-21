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
        Schema::create('customer_o_t_ps', function (Blueprint $table) {
            $table->id('id');
            $table->string('otp')->nullable();
            $table->timestamp('otp_expires_at')->nullable();
            $table->string('phone')->nullable();
            $table->boolean('is_test_mode')->nullable()->default(false);
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
        Schema::drop('customer_o_t_ps');
    }
};
