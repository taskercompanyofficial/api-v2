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
        Schema::create('staff_assets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('staff_id');
            $table->string('asset_type', 50); // laptop, phone, uniform, tools, etc.
            $table->string('asset_name', 200);
            $table->string('asset_model', 100)->nullable();
            $table->string('asset_serial', 100)->nullable();
            $table->string('asset_tag', 50)->unique()->nullable();
            $table->string('manufacturer', 100)->nullable();
            $table->decimal('purchase_cost', 10, 2)->nullable();
            $table->date('purchase_date')->nullable();
            $table->string('vendor', 100)->nullable();
            $table->string('condition', 20)->default('new'); // new, good, fair, poor, damaged
            $table->enum('status', ['assigned', 'available', 'maintenance', 'retired', 'lost', 'damaged'])->default('assigned');
            $table->date('assigned_date');
            $table->date('return_date')->nullable();
            $table->date('expected_return_date')->nullable();
            $table->string('assigned_by', 100)->nullable();
            $table->text('assignment_notes')->nullable();
            $table->string('photo_file', 255)->nullable();
            $table->string('receipt_file', 255)->nullable();
            $table->date('warranty_expiry')->nullable();
            $table->boolean('is_returnable')->default(true);
            $table->text('return_condition')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('staff_id')->references('id')->on('staff')->onDelete('cascade');
            $table->index('staff_id');
            $table->index('asset_type');
            $table->index('status');
            $table->index('asset_tag');
            $table->index('assigned_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('staff_assets');
    }
};