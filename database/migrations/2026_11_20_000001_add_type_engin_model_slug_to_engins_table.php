<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('engins', function (Blueprint $table) {
            // Modèle de véhicule du camion (type_engin_models.slug), utilisé pour inviter les chauffeurs aux courses
            $table->string('type_engin_model_slug')->nullable()->after('type_engin');
            $table->index('type_engin_model_slug');
        });
    }

    public function down(): void
    {
        Schema::table('engins', function (Blueprint $table) {
            $table->dropIndex(['type_engin_model_slug']);
            $table->dropColumn('type_engin_model_slug');
        });
    }
};
