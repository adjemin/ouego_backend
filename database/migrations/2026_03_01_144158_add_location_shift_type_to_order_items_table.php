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
        Schema::table('order_items', function (Blueprint $table) {
            $table->enum('location_shift_type', ['day', 'night', 'double'])
                  ->default('day')
                  ->nullable()
                  ->after('location_end_date')
                  ->comment('Shift type for rental orders: day (journée), night (nuit), double (journée + nuit)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('location_shift_type');
        });
    }
};
