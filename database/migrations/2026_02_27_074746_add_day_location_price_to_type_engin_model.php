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
        Schema::table('type_engin_models', function (Blueprint $table) {
            $table->double('day_location_price')->nullable()->after('price');
            $table->boolean('transport_required_vehicule')->default(false)->after('day_location_price');
            $table->double('transport_km_price')->nullable()->after('transport_required_vehicule');
            $table->double('transport_km_price_with_vehicule')->nullable()->after('transport_km_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('type_engin_models', function (Blueprint $table) {
            $table->dropColumn('day_location_price');
            $table->dropColumn('transport_required_vehicule');
            $table->dropColumn('transport_km_price');
            $table->dropColumn('transport_km_price_with_vehicule');
        });
    }
};
