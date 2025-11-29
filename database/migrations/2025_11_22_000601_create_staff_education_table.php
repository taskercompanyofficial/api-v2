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
        Schema::create('staff_education', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('staff_id');
            $table->string('institution_name', 200);
            $table->string('degree_title', 200);
            $table->string('field_of_study', 100)->nullable();
            $table->enum('education_level', ['high_school', 'diploma', 'bachelor', 'master', 'phd', 'other']);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->boolean('is_completed')->default(true);
            $table->decimal('gpa', 4, 2)->nullable();
            $table->string('grade', 20)->nullable();
            $table->string('certificate_file', 255)->nullable();
            $table->boolean('is_verified')->default(false);
            $table->date('verified_date')->nullable();
            $table->string('verified_by', 100)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('staff_id')->references('id')->on('staff')->onDelete('cascade');
            $table->index('staff_id');
            $table->index('education_level');
            $table->index('is_verified');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('staff_education');
    }
};