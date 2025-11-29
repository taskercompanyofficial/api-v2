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
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('vehicle_number', 50)->unique();
            $table->string('registration_number', 50)->unique();
            $table->string('make', 100);
            $table->string('model', 100);
            $table->integer('year');
            $table->string('color', 50)->nullable();
            $table->string('engine_number', 100)->nullable();
            $table->string('chassis_number', 100)->nullable();
            $table->string('fuel_type', 20)->nullable(); // petrol, diesel, hybrid, electric
            $table->string('transmission_type', 20)->nullable(); // manual, automatic
            $table->integer('seating_capacity')->nullable();
            $table->string('vehicle_category', 50)->nullable(); // car, van, truck, bus, motorcycle
            $table->decimal('purchase_cost', 12, 2)->nullable();
            $table->date('purchase_date')->nullable();
            $table->string('purchase_vendor', 200)->nullable();
            $table->date('registration_date')->nullable();
            $table->date('registration_expiry')->nullable();
            $table->string('insurance_policy_number', 100)->nullable();
            $table->date('insurance_expiry')->nullable();
            $table->string('insurance_provider', 100)->nullable();
            $table->integer('current_mileage')->nullable();
            $table->enum('status', ['available', 'assigned', 'maintenance', 'retired', 'damaged', 'sold'])->default('available');
            $table->string('location', 200)->nullable();
            $table->text('description')->nullable();
            $table->string('photo_file', 255)->nullable();
            $table->string('registration_file', 255)->nullable();
            $table->string('insurance_file', 255)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('vehicle_number');
            $table->index('registration_number');
            $table->index('status');
            $table->index('vehicle_category');
            $table->index('registration_expiry');
            $table->index('insurance_expiry');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};