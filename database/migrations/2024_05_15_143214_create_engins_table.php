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
        Schema::create('engins', function (Blueprint $table) {
            $table->id('id');
            $table->string('driver_id')->nullable();
            $table->string('immatriculation')->nullable()->unique();
            $table->string('numero_carte_grise')->nullable();
            $table->string('brand')->nullable();
            $table->string('serie')->nullable();
            $table->string('type_engin')->nullable();
            $table->string('carrosserie')->nullable();
            $table->string('color')->nullable();
            $table->string('nombre_essieux')->nullable();
            $table->string('nombre_roues')->nullable();
            $table->string('oil')->nullable();
            $table->json('usages')->nullable();
            $table->string('ability_tonne')->nullable();
            $table->string('ptac_tonne')->nullable();
            $table->string('poids_vide')->nullable();
            $table->string('charge_utile')->nullable();
            $table->string('puissance_fiscale')->nullable();
            $table->string('cylindre')->nullable();
            $table->date('date_mise_en_production')->nullable();
            $table->date('date_edition')->nullable();
            $table->string('nom_proprietaire')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('engins');
    }
};
