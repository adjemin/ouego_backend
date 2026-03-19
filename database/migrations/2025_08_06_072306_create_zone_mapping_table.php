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
        Schema::dropIfExists('zone_mapping');

        Schema::create('zone_mapping', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('zone_id')->constrained('zones')->cascadeOnDelete();
            $table->foreignId('carrier_id')->constrained('carriers')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('zone_mapping');
    }
};
