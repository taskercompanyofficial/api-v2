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
        Schema::create('service_concerns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_service_id')->constrained('parent_services')->onDelete('cascade');
            $table->string('name', 100);
            $table->string('slug', 100);
            $table->text('description')->nullable();
            $table->integer('display_order')->default(0);
            $table->string('icon', 50)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Indexes
            $table->index('parent_service_id');
            $table->index('slug');
            $table->index('is_active');
            $table->unique(['parent_service_id', 'slug'], 'unique_slug_per_parent');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_concerns');
    }
};
