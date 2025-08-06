<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('order_invitations', function (Blueprint $table) {
            $table->unsignedBigInteger('trip_request_id')->nullable();
            $table->integer('index')->default(0);
            $table->integer('attempt_number')->default(0);
            $table->string('status')->default('pending');
            ;
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_invitations', function (Blueprint $table) {
            //
        });
    }
};
