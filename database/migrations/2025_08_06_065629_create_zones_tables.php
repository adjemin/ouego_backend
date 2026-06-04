<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
Use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('zone_mapping')) {
            DB::statement('ALTER TABLE zone_mapping DROP CONSTRAINT IF EXISTS zone_mapping_zone_id_foreign');
        }
        Schema::dropIfExists('zones');

        Schema::create('zones', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text("description")->nullable();
            $table->foreignId('zone_base_id')->nullable()->constrained('zones')->nullOnDelete();
            $table->geometry('geometry'); 
            $table->timestamps();
            $table->softDeletes();
        });

        // Créer un index spatial sur le champ geom
        DB::statement('CREATE INDEX idx_zones_geome ON zones USING GIST ("geometry")');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('zone_mapping')) {
            Schema::table('zone_mapping', function (Blueprint $table) {
                $table->dropForeign(['zone_id']);
            });
        }
        Schema::dropIfExists('zones');
    }
};
