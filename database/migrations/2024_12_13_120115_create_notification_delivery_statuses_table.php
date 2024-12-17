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
        Schema::create('notification_delivery_statuses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('notification_id');
            $table->string('fcm_token');
            $table->string('fcm_message_id')->nullable();
            $table->integer('attempt_count')->default(0);
            $table->enum('status', ['PENDING', 'SENT', 'DELIVERED', 'FAILED'])->default('PENDING');
            $table->text('error_message')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->foreign('notification_id')
                  ->references('id')
                  ->on('driver_notifications')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_delivery_statuses');
    }
};
