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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->nullable()->constrained('staff')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('staff')->onDelete('set null');
            $table->string('name');
            $table->string('avatar')->nullable(); // Added based on model
            $table->string('email')->unique()->nullable();
            $table->string('phone')->unique();
            $table->string('whatsapp')->nullable();
            $table->string('customer_id')->unique()->nullable();
            $table->boolean('is_care_of_customer')->default(false);
            $table->enum('status', ['active', 'inactive', 'red_listed', 'vip', 'regular'])->default('active');
            $table->text('description')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
