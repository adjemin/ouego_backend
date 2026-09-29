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
        // Columns were already added in 2026_02_09_212003_create_commercials_table
        if (!Schema::hasColumn('commercials', 'current_balance')) {
            Schema::table('commercials', function (Blueprint $table) {
                $table->double('current_balance')->nullable()->default(0)->after('code');
            });
        }

        if (!Schema::hasColumn('commercials', 'old_balance')) {
            Schema::table('commercials', function (Blueprint $table) {
                $table->double('old_balance')->nullable()->default(0)->after('current_balance');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('commercials', function (Blueprint $table) {
            $table->dropColumn(['current_balance', 'old_balance']);
        });
    }
};
