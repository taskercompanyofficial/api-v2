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
        Schema::create('store_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->nullable()->constrained('staff')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('staff')->onDelete('set null');
            $table->string('name');
            $table->string('slug');
            $table->string('sku');
            $table->decimal('price', 10, 2);
            $table->integer('low_stock_threshold')->nullable();
            $table->text('description')->nullable();
            $table->json('images');
            $table->string('status')->default('active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('store_items');
    }
};
