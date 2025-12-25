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
        Schema::create('api_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Friendly name for the token
            $table->enum('type', ['whatsapp', 'facebook', 'instagram', 'google', 'stripe', 'other'])->default('whatsapp');
            $table->text('token'); // The actual access token (encrypted)
            $table->string('token_type')->default('Bearer'); // Token type (Bearer, API Key, etc.)
            $table->text('refresh_token')->nullable(); // Refresh token if applicable
            $table->timestamp('expires_at')->nullable(); // Token expiration
            $table->json('metadata')->nullable(); // Additional data (app_id, app_secret, etc.)
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('type');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_tokens');
    }
};
