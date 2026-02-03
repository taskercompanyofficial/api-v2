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
        Schema::create('agreement_templates', function (Blueprint $table) {
            $table->id();

            // Template Info
            $table->string('name'); // e.g., "Employee Agreement 2025"
            $table->text('purpose')->nullable(); // Description of agreement

            // Language & Direction
            $table->enum('language', ['en', 'ur', 'mixed'])->default('mixed');
            $table->enum('direction', ['ltr', 'rtl'])->default('ltr');

            // Header & Footer HTML
            $table->text('header_html')->nullable(); // Company branding/header
            $table->text('footer_html')->nullable(); // Signature section template

            // Versioning & Status
            $table->boolean('is_active')->default(true);
            $table->integer('version')->default(1);

            // Audit
            $table->foreignId('created_by')->nullable()->constrained('staff')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('staff')->onDelete('set null');

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('is_active');
            $table->index('language');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agreement_templates');
    }
};
