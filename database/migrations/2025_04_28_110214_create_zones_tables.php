<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class CreateZonesTables extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('zones', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->foreignId('zone_base_id')->nullable()->constrained('zones')->nullOnDelete();
            $table->geometry('geom'); // Champ spatial (PostGIS)
            $table->timestamps();
        });

        // Créer un index spatial sur le champ geom
        DB::statement('CREATE INDEX idx_zones_geom ON zones USING GIST (geom)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('zones');
    }
}
