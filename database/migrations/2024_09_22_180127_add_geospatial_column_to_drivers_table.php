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

        // Assurez-vous que l'extension PostGIS est activée
        DB::statement('CREATE EXTENSION IF NOT EXISTS postgis');

        // Ajoutez la colonne géospatiale
        DB::statement('ALTER TABLE drivers ADD COLUMN last_location GEOGRAPHY(POINT, 4326)');

        // Créez l'index spatial
        DB::statement('CREATE INDEX drivers_last_location_index ON drivers USING GIST (last_location)');

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Supprimez l'index
        Schema::table('drivers', function (Blueprint $table) {
            $table->dropIndex('drivers_last_location_index');
        });

        // Supprimez la colonne
        DB::statement('ALTER TABLE drivers DROP COLUMN last_location');
    }
};
