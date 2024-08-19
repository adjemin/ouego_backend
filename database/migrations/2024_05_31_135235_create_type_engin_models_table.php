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
        Schema::create('type_engin_models', function (Blueprint $table) {
            $table->id('id');
            $table->string('type_engin_slug')->nullable();
            $table->string('title')->nullable();
            $table->string('subtitle')->nullable();
            $table->string('group_tag')->nullable();
            $table->string('slug')->unique();
            $table->string('serie')->nullable();
            $table->string('carrosserie')->nullable();
            $table->string('nombre_essieux')->nullable();
            $table->string('nombre_roues')->nullable();
            $table->string('oil')->nullable();
            $table->string('ability_tonne')->nullable();
            $table->string('ptac_tonne')->nullable();
            $table->string('poids_vide')->nullable();
            $table->string('charge_utile')->nullable();
            $table->string('puissance_fiscale')->nullable();
            $table->string('cylindree')->nullable();
            $table->double('price')->nullable();
            $table->string('currency_code')->nullable();

            $table->integer('ride_base_pricing')->nullable();
            $table->double('slice_1_max_distance')->nullable();
            $table->integer('slice_1_pricing')->nullable();
            $table->double('slice_2_max_distance')->nullable();
            $table->integer('slice_2_pricing')->nullable();
            $table->integer('slice_3_pricing')->nullable();
            $table->integer('manutention_pricing')->nullable();

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
        Schema::drop('type_engin_models');
    }
};
