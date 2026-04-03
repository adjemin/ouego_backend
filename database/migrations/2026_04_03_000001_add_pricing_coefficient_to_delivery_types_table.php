<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('delivery_types', function (Blueprint $table) {
            $table->enum('pricing_operator', ['add', 'subtract', 'multiply', 'divide', 'add_percent', 'subtract_percent'])->default('multiply')->after('is_active');
            $table->decimal('pricing_value', 10, 4)->default(1.0)->after('pricing_operator');
        });
    }

    public function down()
    {
        Schema::table('delivery_types', function (Blueprint $table) {
            $table->dropColumn(['pricing_operator', 'pricing_value']);
        });
    }
};
