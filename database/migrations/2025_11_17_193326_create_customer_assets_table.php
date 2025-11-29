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
        Schema::create('customer_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->foreignId('asset_id')->unique();
            $table->string('product_category');
            $table->string('product_model')->nullable();
            $table->string('indoor_serial')->unique()->nullable();
            $table->string('outdoor_serial')->unique()->nullable();
            $table->date('date_of_purchase')->nullable();
            $table->string('dealer_name')->nullable();
            $table->text('description')->nullable();
            $table->json('images')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_assets');
    }
};
