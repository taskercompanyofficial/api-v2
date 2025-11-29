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
        Schema::create('vehicle_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vehicle_id');
            $table->unsignedBigInteger('staff_id')->nullable();
            $table->unsignedBigInteger('assignment_id')->nullable();
            $table->date('usage_date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->integer('start_mileage');
            $table->integer('end_mileage')->nullable();
            $table->integer('distance_traveled')->nullable();
            $table->string('purpose', 200)->nullable();
            $table->string('route_description', 500)->nullable();
            $table->string('start_location', 200)->nullable();
            $table->string('end_location', 200)->nullable();
            $table->decimal('fuel_consumed', 8, 2)->nullable();
            $table->decimal('fuel_cost', 10, 2)->nullable();
            $table->string('fuel_type', 20)->nullable();
            $table->enum('usage_type', ['official', 'personal', 'mixed'])->default('official');
            $table->text('notes')->nullable();
            $table->boolean('is_approved')->default(false);
            $table->string('approved_by', 100)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('vehicle_id')->references('id')->on('vehicles')->onDelete('cascade');
            $table->foreign('staff_id')->references('id')->on('staff')->onDelete('set null');
            $table->foreign('assignment_id')->references('id')->on('vehicle_assignments')->onDelete('set null');
            $table->index('vehicle_id');
            $table->index('staff_id');
            $table->index('assignment_id');
            $table->index('usage_date');
            $table->index('is_approved');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicle_usage_logs');
    }
};