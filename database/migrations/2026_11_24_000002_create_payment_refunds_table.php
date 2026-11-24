<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remboursements envoyés par l'opérateur au client : un seul par paiement (unicité sur payment_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_refunds', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('payment_id')->unique();
            $table->unsignedBigInteger('order_id')->nullable()->index();
            $table->string('provider');
            $table->unsignedBigInteger('amount');
            $table->string('currency_code')->default('XOF');
            $table->string('reference')->unique();
            $table->string('provider_refund_id')->nullable();
            $table->string('recipient_phone')->nullable();
            $table->string('recipient_method')->nullable();
            $table->string('status')->index();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('next_attempt_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_refunds');
    }
};
