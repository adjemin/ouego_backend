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
        Schema::create('drivers', function (Blueprint $table) {
            $table->id('id');
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('name')->nullable();
            $table->string('dialing_code')->nullable()->default("225");
            $table->string('phone_number')->nullable();
            $table->string('phone')->nullable();
            $table->string('photo_url')->nullable();
            $table->boolean('is_active')->nullable()->default(false);
            $table->double('current_balance')->nullable()->default(0);
            $table->double('old_balance')->nullable()->default(0);
            $table->double('last_location_latitude')->nullable();
            $table->double('last_location_longitude')->nullable();
            $table->boolean('is_available')->nullable()->default(false);
            $table->json('services')->nullable();
            $table->json('driver_license_docs')->nullable();
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
        Schema::drop('drivers');
    }
};
