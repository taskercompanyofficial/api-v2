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
        Schema::create('staff_training', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('staff_id');
            $table->string('training_title', 200);
            $table->string('training_provider', 200);
            $table->string('training_type', 50); // internal, external, online, workshop, seminar
            $table->string('training_category', 100)->nullable(); // technical, soft_skills, compliance, safety
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->integer('duration_hours')->nullable();
            $table->string('location', 200)->nullable();
            $table->string('instructor_name', 100)->nullable();
            $table->decimal('cost', 10, 2)->nullable();
            $table->string('currency', 3)->default('PKR');
            $table->enum('status', ['scheduled', 'in_progress', 'completed', 'cancelled', 'postponed'])->default('scheduled');
            $table->enum('completion_status', ['pending', 'passed', 'failed', 'incomplete'])->default('pending');
            $table->decimal('score', 5, 2)->nullable();
            $table->string('certificate_file', 255)->nullable();
            $table->boolean('is_mandatory')->default(false);
            $table->date('expiry_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('staff_id')->references('id')->on('staff')->onDelete('cascade');
            $table->index('staff_id');
            $table->index('training_type');
            $table->index('status');
            $table->index('completion_status');
            $table->index('expiry_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('staff_training');
    }
};