<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Expression;
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
        Schema::create('type_engins', function (Blueprint $table) {
            $table->id('id');
            $table->string('name')->nullable();
            $table->string('slug')->unique();
            $table->json('ability_tonne')->nullable();
            $table->string('usage')->nullable();
            $table->json('services')->nullable();
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
        Schema::drop('type_engins');
    }
};
