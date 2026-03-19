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
        Schema::table('commercials', function (Blueprint $table) {
            if (!Schema::hasColumn('commercials', 'current_balance')) {
                $table->double('current_balance')->nullable()->default(0)->after('code');
            }
            if (!Schema::hasColumn('commercials', 'old_balance')) {
                $table->double('old_balance')->nullable()->default(0)->after('current_balance');
            }
        });
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
