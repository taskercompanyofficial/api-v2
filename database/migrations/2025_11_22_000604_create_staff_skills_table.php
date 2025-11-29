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
        Schema::create('staff_skills', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('staff_id');
            $table->string('skill_name', 100);
            $table->string('skill_category', 50); // technical, soft, language, management
            $table->enum('proficiency_level', ['beginner', 'intermediate', 'advanced', 'expert']);
            $table->integer('years_of_experience')->nullable();
            $table->date('last_used_date')->nullable();
            $table->boolean('is_certified')->default(false);
            $table->string('certification_body', 100)->nullable();
            $table->date('certification_date')->nullable();
            $table->date('certification_expiry')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_primary_skill')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('staff_id')->references('id')->on('staff')->onDelete('cascade');
            $table->index('staff_id');
            $table->index('skill_category');
            $table->index('proficiency_level');
            $table->index('is_primary_skill');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('staff_skills');
    }
};