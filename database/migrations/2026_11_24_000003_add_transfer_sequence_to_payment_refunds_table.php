<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Numéro du transfert en cours pour un remboursement : la référence envoyée à l'opérateur est
 * "{reference}-{transfer_sequence}". Il n'augmente qu'après un refus définitif, jamais après une réponse
 * perdue : un nouvel essai réutilise alors la même référence et l'opérateur dédoublonne (409).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_refunds', function (Blueprint $table) {
            $table->unsignedInteger('transfer_sequence')->default(1)->after('reference');
        });
    }

    public function down(): void
    {
        Schema::table('payment_refunds', function (Blueprint $table) {
            $table->dropColumn('transfer_sequence');
        });
    }
};
