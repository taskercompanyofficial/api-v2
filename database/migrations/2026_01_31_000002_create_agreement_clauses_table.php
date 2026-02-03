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
        Schema::create('agreement_clauses', function (Blueprint $table) {
            $table->id();

            // Link to template
            $table->foreignId('agreement_template_id')->constrained('agreement_templates')->onDelete('cascade');

            // Clause Info
            $table->string('clause_number', 20)->nullable(); // e.g., "1", "2.a", "3"
            $table->string('title')->nullable(); // Section heading (optional)
            $table->text('content'); // The actual clause text

            // Language & Direction per clause
            $table->enum('language', ['en', 'ur', 'mixed'])->default('ur');
            $table->enum('direction', ['ltr', 'rtl'])->default('rtl');

            // Display Control
            $table->integer('display_order')->default(0); // Sort order
            $table->boolean('is_active')->default(true);

            // Audit
            $table->foreignId('created_by')->nullable()->constrained('staff')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('staff')->onDelete('set null');

            $table->timestamps();

            // Indexes
            $table->index(['agreement_template_id', 'display_order']);
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agreement_clauses');
    }
};
