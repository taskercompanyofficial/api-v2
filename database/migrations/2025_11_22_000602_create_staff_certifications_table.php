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
        Schema::create('staff_certifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('staff_id');
            $table->string('certification_name', 200);
            $table->string('issuing_organization', 200);
            $table->string('certification_number', 100)->nullable();
            $table->date('issue_date');
            $table->date('expiry_date')->nullable();
            $table->boolean('has_expiry')->default(true);
            $table->string('credential_url', 255)->nullable();
            $table->string('certificate_file', 255)->nullable();
            $table->boolean('is_verified')->default(false);
            $table->date('verified_date')->nullable();
            $table->string('verified_by', 100)->nullable();
            $table->enum('status', ['active', 'expired', 'revoked', 'pending'])->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('staff_id')->references('id')->on('staff')->onDelete('cascade');
            $table->index('staff_id');
            $table->index('status');
            $table->index('is_verified');
            $table->index('expiry_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('staff_certifications');
    }
};