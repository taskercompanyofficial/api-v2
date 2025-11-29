<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('authorized_brands', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('service_type')->nullable();
            $table->string('logo_image')->nullable();

            // Use just "images" (json) for gallery, as in model/controller
            $table->json('images')->nullable();

            // Remove unused columns: tariffs, policy_image, jobsheet_file, bill_format_file
            // Use just "documents" as a single JSON column for all docs (as in the form/controller/model)
            $table->json('documents')->nullable();

            // New arrays for parts/services/materials
            $table->json('warranty_parts')->nullable();
            $table->json('service_charges')->nullable();
            $table->json('materials')->nullable();

            $table->date('billing_date')->nullable();
            $table->string('status')->default('active');
            $table->boolean('is_authorized')->default(true);
            $table->boolean('is_available_for_warranty')->default(false);
            $table->boolean('has_free_installation_service')->default(false);

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index(['status', 'service_type']);
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('authorized_brands');
    }
};
