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
        Schema::create('cashkdiopen_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('transaction_id');
            $table->string('payment_method', 20);
            $table->unsignedBigInteger('amount'); // Amount in cents
            $table->string('currency', 3);
            $table->enum('status', [
                'pending',
                'processing', 
                'success', 
                'failed', 
                'canceled'
            ])->default('pending')->index();
            $table->string('provider_payment_id', 100)->nullable()->index();
            $table->json('provider_data')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            // Indexes
            $table->index(['transaction_id', 'status']);
            $table->index(['payment_method', 'status']);
            $table->index(['status', 'created_at']);

            // Foreign key constraint
            $table->foreign('transaction_id')
                  ->references('id')
                  ->on('cashkdiopen_transactions')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cashkdiopen_payments');
    }
};