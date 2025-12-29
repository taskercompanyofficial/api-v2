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
        Schema::create('dealers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->nullable()->constrained('staff')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('staff')->onDelete('set null');

            // Business Information
            $table->string('name'); // Shop/Business Name
            $table->string('slug')->unique();
            $table->string('business_type')->nullable(); // retail, wholesale, distributor, etc.
            $table->string('license_number')->nullable();
            $table->string('registration_number')->nullable();

            // Contact Information
            $table->string('phone');
            $table->string('whatsapp')->nullable();
            $table->string('email')->nullable()->unique();

            // Owner/Contact Person Information
            $table->string('owner_name')->nullable();
            $table->string('owner_phone')->nullable();
            $table->string('contact_person_name')->nullable();
            $table->string('contact_person_phone')->nullable();

            // Address Information
            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('zip_code')->nullable();
            $table->string('country')->default('Pakistan');
            $table->enum('area_type', ['residential', 'commercial', 'industrial', 'other'])->default('commercial');
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();

            // Business Details
            $table->json('products_handled')->nullable(); // Array of product categories they work with
            $table->json('service_areas')->nullable(); // Areas they service
            $table->decimal('credit_limit', 15, 2)->nullable();
            $table->date('agreement_start_date')->nullable();
            $table->date('agreement_end_date')->nullable();
            $table->decimal('commission_rate', 5, 2)->nullable(); // Percentage

            // Media & Documents
            $table->string('logo')->nullable();
            $table->json('images')->nullable(); // Gallery images
            $table->json('documents')->nullable(); // License, agreements, etc.

            // Status & Settings
            $table->enum('status', ['active', 'inactive', 'suspended', 'pending_approval'])->default('pending_approval');
            $table->boolean('is_verified')->default(false);
            $table->boolean('can_create_branches')->default(true);
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['status', 'city', 'state']);
            $table->index(['business_type', 'status']);
            $table->index('is_verified');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dealers');
    }
};
