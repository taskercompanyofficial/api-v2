<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds bot_disabled column to allow staff takeover of conversations.
     */
    public function up(): void
    {
        Schema::table('whatsapp_conversations', function (Blueprint $table) {
            $table->boolean('bot_disabled')->default(false)->after('status');
            $table->timestamp('bot_disabled_at')->nullable()->after('bot_disabled');
            $table->foreignId('bot_disabled_by')->nullable()->after('bot_disabled_at')
                ->constrained('staff')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_conversations', function (Blueprint $table) {
            $table->dropForeign(['bot_disabled_by']);
            $table->dropColumn(['bot_disabled', 'bot_disabled_at', 'bot_disabled_by']);
        });
    }
};
