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
        Schema::create('cashkdiopen_webhook_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('transaction_id')->nullable();
            $table->string('provider', 20)->index();
            $table->string('event_type', 50)->index();
            $table->json('payload');
            $table->json('headers');
            $table->string('signature', 100)->nullable();
            $table->enum('status', [
                'pending',
                'processing', 
                'success', 
                'failed', 
                'ignored'
            ])->default('pending')->index();
            $table->text('error_message')->nullable();
            $table->integer('retry_count')->default(0);
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            // Indexes for common queries
            $table->index(['transaction_id', 'status']);
            $table->index(['provider', 'status']);
            $table->index(['event_type', 'status']);
            $table->index(['status', 'created_at']);
            $table->index(['retry_count', 'status']);
            $table->index(['created_at']); // For cleanup operations

            // Foreign key constraint (nullable, so no cascade)
            $table->foreign('transaction_id')
                  ->references('id')
                  ->on('cashkdiopen_transactions')
                  ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cashkdiopen_webhook_logs');
    }
};