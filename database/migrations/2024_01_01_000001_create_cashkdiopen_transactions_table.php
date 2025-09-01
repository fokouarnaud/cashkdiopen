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
        Schema::create('cashkdiopen_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('reference', 50)->unique()->index();
            $table->string('provider', 20)->index();
            $table->unsignedBigInteger('amount'); // Amount in cents
            $table->string('currency', 3)->default('XOF');
            $table->string('customer_phone', 20)->nullable();
            $table->text('description');
            $table->enum('status', [
                'pending', 
                'processing', 
                'success', 
                'failed', 
                'canceled', 
                'expired'
            ])->default('pending')->index();
            $table->string('provider_reference', 100)->nullable()->index();
            $table->json('provider_response')->nullable();
            $table->string('callback_url', 500);
            $table->string('return_url', 500);
            $table->json('metadata')->nullable();
            $table->uuid('api_key_id')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Composite indexes for common queries
            $table->index(['status', 'created_at']);
            $table->index(['provider', 'status']);
            $table->index(['expires_at', 'status']);
            $table->index(['api_key_id', 'created_at']);
            $table->index(['currency', 'amount']);

            // Foreign key constraint (will be added if api_keys table exists)
            // $table->foreign('api_key_id')->references('id')->on('cashkdiopen_api_keys')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cashkdiopen_transactions');
    }
};