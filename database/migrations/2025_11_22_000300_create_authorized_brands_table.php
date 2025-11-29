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
            $table->string('policy_image')->nullable();
            $table->json('images')->nullable();
            $table->json('tariffs')->nullable();
            $table->string('jobsheet_file')->nullable();
            $table->string('bill_format_file')->nullable();
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