<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('driver_carriers', function (Blueprint $table) {
            $table->id('id');

            // Clé étrangère pour driver_id
            $table->unsignedBigInteger('driver_id');
            $table->foreign('driver_id')
                    ->references('id')
                    ->on('drivers')
                    ->onDelete('cascade');

            // Clé étrangère pour carrier_id
            $table->unsignedBigInteger('carrier_id');
            $table->foreign('carrier_id')
                    ->references('id')
                    ->on('carriers')
                    ->onDelete('cascade');

            // Index composite pour optimiser les requêtes
            $table->index(['driver_id', 'carrier_id']);

            // Contrainte d'unicité pour éviter les doublons
            $table->unique(['driver_id', 'carrier_id']);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('driver_carriers');
    }
};
