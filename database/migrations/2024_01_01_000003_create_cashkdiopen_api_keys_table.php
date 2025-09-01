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
        Schema::create('cashkdiopen_api_keys', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('name', 100);
            $table->string('key_id', 50)->unique()->index();
            $table->text('key_secret'); // Encrypted
            $table->enum('environment', ['sandbox', 'production'])->default('sandbox')->index();
            $table->json('scopes')->nullable();
            $table->integer('rate_limit')->default(1000);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['user_id', 'environment']);
            $table->index(['environment', 'created_at']);
            $table->index(['last_used_at']);
            $table->index(['expires_at']);

            // Add foreign key constraint only if users table exists
            try {
                if (Schema::hasTable('users')) {
                    $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
                }
            } catch (\Exception $e) {
                // Table might not exist in package context
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cashkdiopen_api_keys');
    }
};