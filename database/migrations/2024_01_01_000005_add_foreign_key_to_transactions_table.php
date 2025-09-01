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
        Schema::table('cashkdiopen_transactions', function (Blueprint $table) {
            // Add foreign key constraint to api_keys table
            // This is done in a separate migration to handle table creation order
            $table->foreign('api_key_id')
                  ->references('id')
                  ->on('cashkdiopen_api_keys')
                  ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cashkdiopen_transactions', function (Blueprint $table) {
            $table->dropForeign(['api_key_id']);
        });
    }
};