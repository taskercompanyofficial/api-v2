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
        Schema::create('business_phone_numbers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('api_token_id')->constrained('api_tokens')->onDelete('cascade');
            $table->string('phone_number')->unique(); // E.164 format: +1234567890
            $table->string('phone_number_id')->unique(); // WhatsApp Phone Number ID
            $table->string('business_account_id'); // WhatsApp Business Account ID
            $table->string('display_name')->nullable(); // Business display name
            $table->enum('platform', ['whatsapp', 'telegram', 'viber', 'other'])->default('whatsapp');
            $table->enum('status', ['active', 'inactive', 'pending_verification', 'suspended'])->default('pending_verification');
            $table->string('verification_code')->nullable(); // For verification process
            $table->timestamp('verified_at')->nullable();
            $table->json('capabilities')->nullable(); // Messaging capabilities
            $table->json('metadata')->nullable(); // Additional platform-specific data
            $table->boolean('is_default')->default(false); // Default number for sending
            $table->timestamps();
            $table->softDeletes();

            $table->index('phone_number');
            $table->index('platform');
            $table->index('status');
            $table->index('is_default');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('business_phone_numbers');
    }
};
