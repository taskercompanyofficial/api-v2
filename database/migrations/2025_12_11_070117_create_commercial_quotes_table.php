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
        Schema::create('commercial_quotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade');
            $table->string('organization_name');
            $table->string('business_type');
            $table->string('contact_person');
            $table->string('email');
            $table->string('phone');
            $table->text('address');
            $table->string('facility_size')->nullable();
            $table->json('services'); // Array of selected services
            $table->text('description')->nullable();
            $table->enum('status', ['pending', 'reviewed', 'quoted', 'accepted', 'rejected'])->default('pending');
            $table->decimal('quoted_amount', 10, 2)->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commercial_quotes');
    }
};
