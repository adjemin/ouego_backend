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
        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('name_en', 225)->nullable();
            $table->string('native', 225)->nullable();
            $table->string('code');
            $table->string('flag')->nullable();
            $table->string('dial_code')->nullable();
            $table->string('language')->nullable();
            $table->string('continent_code', 225)->nullable();
            $table->string('continent_name', 225)->nullable();
            $table->string('latlng', 225)->nullable();
            $table->string('location_lat')->nullable();
            $table->string('location_lng')->nullable();
            $table->string('currency_code')->nullable();
            $table->string('capital', 225)->nullable();
            $table->text('timezone')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('countries');
    }
};
