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
        Schema::create('benchmarks', function (Blueprint $table) {
            $table->id();
            $table->string('category')->index(); // 'kpi_resolution', 'nps'
            $table->string('key')->index(); // 'same_day', '2_day', ..., 'nps_score', etc
            $table->string('label')->nullable();
            $table->decimal('target_value', 5, 2)->default(0); // Target % or Score
            $table->integer('min_value')->nullable(); // For buckets/NPS ranges
            $table->integer('max_value')->nullable(); // For buckets/NPS ranges
            $table->integer('order_index')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('benchmarks');
    }
};
