<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('store_item_instances', function (Blueprint $table) {
            $table->id();
            $table->string('item_instance_id')->unique();
            $table->foreignId('created_by')->nullable()->constrained('staff')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('staff')->onDelete('set null');
            $table->string('complaint_number')->nullable()->constrained('complaints')->onDelete('set null');
            $table->foreignId('assigned_to')->nullable()->constrained('staff')->onDelete('set null');
            $table->foreignId('store_item_id')->nullable()->constrained('store_items')->onDelete('set null');
            $table->string('barcode');
            $table->string('description')->nullable();
            $table->decimal('used_price', 10, 2)->nullable();
            $table->date('used_date')->nullable();
            $table->json('images')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('store_item_instances');
    }
};
