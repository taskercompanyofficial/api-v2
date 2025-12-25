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
        Schema::create('whatsapp_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('whatsapp_contact_id')->constrained('whatsapp_contacts')->onDelete('cascade');
            $table->foreignId('customer_id')->nullable()->constrained('customers')->onDelete('cascade');
            $table->string('whatsapp_conversation_id')->nullable(); // WhatsApp's conversation ID
            $table->enum('status', ['open', 'closed', 'archived'])->default('open');
            $table->timestamp('last_message_at')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->onDelete('set null'); // Staff member assigned
            $table->text('notes')->nullable(); // Internal notes about conversation
            $table->timestamps();
            $table->softDeletes();

            $table->index('whatsapp_contact_id');
            $table->index('customer_id');
            $table->index('status');
            $table->index('assigned_to');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_conversations');
    }
};
