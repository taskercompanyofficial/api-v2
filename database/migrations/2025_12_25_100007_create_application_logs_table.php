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
        Schema::create('application_logs', function (Blueprint $table) {
            $table->id();
            $table->string('request_id')->nullable()->index(); // For tracking related logs
            $table->enum('level', ['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'])->default('info');
            $table->string('channel')->default('application'); // whatsapp, api, database, etc.
            $table->text('message');
            $table->json('context')->nullable(); // Additional data
            $table->string('exception_class')->nullable(); // Exception class name if error
            $table->text('exception_message')->nullable();
            $table->longText('stack_trace')->nullable();
            $table->string('file')->nullable(); // File where log originated
            $table->integer('line')->nullable(); // Line number
            $table->string('user_id')->nullable(); // User who triggered the log
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->string('url')->nullable();
            $table->string('method')->nullable(); // GET, POST, etc.
            $table->timestamp('created_at')->index();

            $table->index('level');
            $table->index('channel');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('application_logs');
    }
};
