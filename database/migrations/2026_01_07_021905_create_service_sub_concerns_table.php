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
        Schema::create('service_sub_concerns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_concern_id')->constrained('service_concerns')->onDelete('cascade');
            $table->string('name', 100);
            $table->string('slug', 100);
            $table->string('code', 20)->nullable()->comment('For error codes like E1, E2, H1');
            $table->text('description')->nullable();
            $table->text('solution_guide')->nullable()->comment('Help text for technicians');
            $table->integer('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Indexes
            $table->index('service_concern_id');
            $table->index('slug');
            $table->index('code');
            $table->index('is_active');
            $table->unique(['service_concern_id', 'slug'], 'unique_slug_per_concern');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_sub_concerns');
    }
};
