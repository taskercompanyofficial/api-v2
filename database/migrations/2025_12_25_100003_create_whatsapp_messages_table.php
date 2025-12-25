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
        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('whatsapp_conversation_id')->constrained('whatsapp_conversations')->onDelete('cascade');
            $table->string('whatsapp_message_id')->unique()->nullable(); // WhatsApp's message ID
            $table->enum('direction', ['inbound', 'outbound']);
            $table->enum('type', ['text', 'image', 'video', 'document', 'audio', 'template', 'interactive', 'location', 'contacts', 'sticker'])->default('text');
            $table->text('content')->nullable(); // Text content or caption
            $table->json('media')->nullable(); // Media URLs and metadata
            $table->json('template_data')->nullable(); // Template name and parameters
            $table->json('interactive_data')->nullable(); // Interactive message data (buttons, lists)
            $table->enum('status', ['pending', 'sent', 'delivered', 'read', 'failed'])->default('pending');
            $table->text('error_message')->nullable(); // Error details if failed
            $table->string('error_code')->nullable();
            $table->foreignId('sent_by')->nullable()->constrained('users')->onDelete('set null'); // Staff who sent (for outbound)
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('whatsapp_conversation_id');
            $table->index('whatsapp_message_id');
            $table->index('direction');
            $table->index('status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_messages');
    }
};
