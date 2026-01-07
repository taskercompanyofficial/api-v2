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
        Schema::create('file_requirement_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->text('description')->nullable();

            // Context Filters (ALL optional - NULL means applies to all)
            $table->foreignId('parent_service_id')->nullable()->constrained('parent_services')->onDelete('cascade');
            $table->foreignId('service_concern_id')->nullable()->constrained('service_concerns')->onDelete('cascade');
            $table->foreignId('service_sub_concern_id')->nullable()->constrained('service_sub_concerns')->onDelete('cascade');

            // Warranty/Payment Context
            $table->boolean('is_warranty_case')->nullable()->comment('NULL=both, TRUE=warranty only, FALSE=paid only');

            // Brand/Category specific rules
            $table->foreignId('authorized_brand_id')->nullable()->constrained('authorized_brands')->onDelete('cascade');
            $table->foreignId('category_id')->nullable()->constrained('categories')->onDelete('cascade');

            // The file type required
            $table->foreignId('file_type_id')->constrained('file_types')->onDelete('cascade');

            // Requirement Level
            $table->enum('requirement_type', ['required', 'optional', 'hidden'])->default('required');

            // Conditional Requirements
            $table->string('required_if_field', 50)->nullable()->comment('e.g., warranty_verified');
            $table->string('required_if_value', 100)->nullable()->comment('e.g., true');

            // Display Configuration
            $table->integer('display_order')->default(0);
            $table->text('help_text')->nullable();
            $table->json('validation_rules')->nullable()->comment('max_size_mb, allowed_types, etc.');

            // Rule Priority (higher = more specific, wins)
            $table->integer('priority')->default(0);

            // Status
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Indexes
            $table->index('parent_service_id');
            $table->index('service_concern_id');
            $table->index('service_sub_concern_id');
            $table->index('authorized_brand_id');
            $table->index('category_id');
            $table->index('file_type_id');
            $table->index('is_warranty_case');
            $table->index('is_active');
            $table->index('priority');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('file_requirement_rules');
    }
};
