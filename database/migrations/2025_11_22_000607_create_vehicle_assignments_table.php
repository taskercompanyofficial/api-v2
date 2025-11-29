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
        Schema::create('vehicle_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vehicle_id');
            $table->unsignedBigInteger('staff_id');
            $table->date('assignment_date');
            $table->date('expected_return_date')->nullable();
            $table->date('actual_return_date')->nullable();
            $table->integer('start_mileage')->nullable();
            $table->integer('end_mileage')->nullable();
            $table->string('assignment_type', 50)->default('temporary'); // temporary, permanent, loan
            $table->string('purpose', 200)->nullable();
            $table->text('assignment_notes')->nullable();
            $table->text('return_notes')->nullable();
            $table->string('assigned_by', 100)->nullable();
            $table->enum('status', ['active', 'returned', 'overdue', 'cancelled'])->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('vehicle_id')->references('id')->on('vehicles')->onDelete('cascade');
            $table->foreign('staff_id')->references('id')->on('staff')->onDelete('cascade');
            $table->index('vehicle_id');
            $table->index('staff_id');
            $table->index('status');
            $table->index('assignment_date');
            $table->index('expected_return_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicle_assignments');
    }
};