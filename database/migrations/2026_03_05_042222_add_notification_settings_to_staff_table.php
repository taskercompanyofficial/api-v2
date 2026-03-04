<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->boolean('push_enabled')->default(true);
            $table->boolean('email_enabled')->default(false);
            $table->boolean('sound_enabled')->default(true);
            $table->boolean('vibrate_enabled')->default(true);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->dropColumn(['push_enabled', 'email_enabled', 'sound_enabled', 'vibrate_enabled']);
        });
    }
};
