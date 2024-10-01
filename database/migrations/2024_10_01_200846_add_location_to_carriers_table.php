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
        Schema::table('carriers', function (Blueprint $table) {
            //
            // Ajoutez la nouvelle colonne de type point
            DB::statement('ALTER TABLE carriers ADD COLUMN location GEOGRAPHY(POINT, 4326)');
        });

         // Ajoutez un index spatial
         DB::statement('CREATE INDEX carriers_location_index ON carriers USING GIST (location)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('carriers', function (Blueprint $table) {
            //
            // Supprimez la colonne de localisation et l'index
            DB::statement('DROP INDEX IF EXISTS carriers_location_index');
            $table->dropColumn('location');
        });
    }
};
