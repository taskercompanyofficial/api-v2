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
        Schema::create('free_installations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade');
            $table->string('contact_person');
            $table->string('phone');
            $table->enum('product_type', ['washing_machine', 'air_conditioner']);
            $table->foreignId('brand_id')->constrained('authorized_brands');
            $table->string('product_model');
            $table->date('purchase_date');
            $table->string('invoice_image');
            $table->string('warranty_image');
            $table->boolean('is_delivered')->default(false);
            $table->boolean('is_wiring_done')->default(false);
            $table->boolean('outdoor_bracket_needed')->default(false);
            $table->integer('extra_pipes')->default(0);
            $table->integer('extra_holes')->default(0);
            $table->string('city');
            $table->enum('status', ['pending', 'approved', 'scheduled', 'in_progress', 'completed', 'rejected'])->default('pending');
            $table->foreignId('order_id')->nullable()->constrained('orders')->onDelete('set null');
            $table->text('admin_notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('free_installations');
    }
};
