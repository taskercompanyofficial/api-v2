<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gift_vouchers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            $table->enum('type', ['daily_checkin', 'booking_complete', 'promo'])->default('promo');
            $table->string('title');
            $table->unsignedInteger('discount_percent')->nullable();
            $table->decimal('amount', 12, 2)->nullable();
            $table->enum('status', ['unclaimed', 'claimed', 'redeemed'])->default('unclaimed');
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('redeemed_at')->nullable();
            $table->timestamps();
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
            $table->index(['customer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gift_vouchers');
    }
};
