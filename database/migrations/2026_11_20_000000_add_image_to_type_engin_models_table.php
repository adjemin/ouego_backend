<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('type_engin_models', function (Blueprint $table) {
            // URL de l'image du modèle d'engin
            $table->string('image')->nullable()->after('subtitle');
        });
    }

    public function down(): void
    {
        Schema::table('type_engin_models', function (Blueprint $table) {
            $table->dropColumn('image');
        });
    }
};
