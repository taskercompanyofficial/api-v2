<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Creates the whatsapp_conversation_staff table for many-to-many relationship
     * between conversations and staff members who can view/manage them.
     */
    public function up(): void
    {
        Schema::create('whatsapp_conversation_staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('whatsapp_conversation_id')
                ->constrained('whatsapp_conversations')
                ->onDelete('cascade');
            $table->foreignId('user_id')
                ->constrained('users')
                ->onDelete('cascade');
            $table->enum('role', ['viewer', 'assigned', 'owner'])->default('viewer');
            $table->boolean('notifications_enabled')->default(true);
            $table->timestamp('last_viewed_at')->nullable();
            $table->timestamps();

            // Unique constraint: each staff can only be assigned once per conversation
            $table->unique(['whatsapp_conversation_id', 'user_id'], 'conversation_staff_unique');

            // Indexes for faster queries
            $table->index('user_id');
            $table->index('role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_conversation_staff');
    }
};
