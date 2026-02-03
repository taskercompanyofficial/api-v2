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
        Schema::create('staff_agreements', function (Blueprint $table) {
            $table->id();

            // Links
            $table->foreignId('staff_id')->constrained('staff')->onDelete('cascade');
            $table->foreignId('agreement_template_id')->constrained('agreement_templates')->onDelete('restrict');

            // Generated Content
            $table->longText('generated_html')->nullable(); // Full rendered HTML
            $table->string('pdf_path', 500)->nullable(); // Path to PDF

            // Status
            $table->enum('status', [
                'draft',
                'pending_signature',
                'partially_signed',
                'signed',
                'active',
                'terminated',
                'expired'
            ])->default('draft');

            // Employee Signature
            $table->timestamp('employee_signed_at')->nullable();
            $table->text('employee_signature_data')->nullable(); // Base64 signature image
            $table->string('employee_ip_address', 45)->nullable();

            // CEO Signature
            $table->timestamp('ceo_signed_at')->nullable();
            $table->text('ceo_signature_data')->nullable();
            $table->foreignId('ceo_signed_by')->nullable()->constrained('staff')->onDelete('set null');

            // General Manager Signature
            $table->timestamp('gm_signed_at')->nullable();
            $table->text('gm_signature_data')->nullable();
            $table->foreignId('gm_signed_by')->nullable()->constrained('staff')->onDelete('set null');

            // Agreement Dates
            $table->date('effective_date')->nullable(); // When agreement starts
            $table->date('expiry_date')->nullable(); // When agreement ends (if applicable)
            $table->date('terminated_at')->nullable(); // If terminated early
            $table->text('termination_reason')->nullable();

            // Additional Data
            $table->json('custom_fields')->nullable(); // Any extra data specific to this agreement
            $table->text('notes')->nullable(); // Internal notes

            // Audit
            $table->foreignId('created_by')->nullable()->constrained('staff')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('staff')->onDelete('set null');

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['staff_id', 'status']);
            $table->index('agreement_template_id');
            $table->index('effective_date');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('staff_agreements');
    }
};
