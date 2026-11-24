<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rattache chaque paiement à l'opérateur qui l'a créé (Jèko…) et rend la référence unique,
 * car elle sert d'identifiant chez l'opérateur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('provider')->nullable();
            $table->string('provider_payment_id')->nullable()->index();
            $table->text('payment_url')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->unsignedInteger('check_attempts')->default(0);
        });

        // Les anciennes références (md5(time()) tronqué) peuvent se répéter : on suffixe les doublons par l'id
        DB::statement("
            UPDATE payments SET payment_reference = payment_reference || '-' || id
            WHERE id IN (
                SELECT id FROM (
                    SELECT id, ROW_NUMBER() OVER (PARTITION BY payment_reference ORDER BY id) AS rn
                    FROM payments WHERE payment_reference IS NOT NULL
                ) d WHERE d.rn > 1
            )
        ");

        Schema::table('payments', function (Blueprint $table) {
            $table->unique('payment_reference');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique(['payment_reference']);
            $table->dropIndex(['provider_payment_id']);
            $table->dropColumn(['provider', 'provider_payment_id', 'payment_url', 'expires_at', 'last_checked_at', 'check_attempts']);
        });
    }
};
