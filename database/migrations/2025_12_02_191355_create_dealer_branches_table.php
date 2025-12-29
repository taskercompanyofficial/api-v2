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
        Schema::create('dealer_branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->nullable()->constrained('staff')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('staff')->onDelete('set null');
            $table->foreignId('dealer_id')->constrained('dealers')->onDelete('cascade');

            // Branch Information
            $table->string('name'); // Branch Name
            $table->string('slug')->unique();
            $table->string('branch_code')->unique(); // Dealer-specific branch code
            $table->string('branch_designation')->nullable(); // Main, Secondary, etc.

            // Contact Information
            $table->string('phone');
            $table->string('whatsapp')->nullable();
            $table->string('email')->nullable();

            // Branch Contact Person Information
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
            $table->json('products_handled')->nullable(); // Products this branch handles
            $table->json('service_areas')->nullable(); // Areas this branch services
            $table->decimal('monthly_target', 15, 2)->nullable();
            $table->json('opening_hours')->nullable(); // Same structure as our_branches

            // Media & Documents
            $table->string('image')->nullable(); // Main branch image
            $table->json('images')->nullable(); // Gallery images

            // Status & Settings
            $table->enum('status', ['active', 'inactive', 'temporarily_closed'])->default('active');
            $table->boolean('is_main_branch')->default(false);
            $table->boolean('visible_to_customers')->default(true);
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['dealer_id', 'status']);
            $table->index(['city', 'state', 'status']);
            $table->index('is_main_branch');
            $table->unique(['dealer_id', 'branch_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dealer_branches');
    }
};
