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
        Schema::create('vendor_ledgers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->onDelete('cascade');
            $table->foreignId('work_order_id')->nullable()->constrained('work_orders')->onDelete('set null');
            
            $table->enum('type', ['credit', 'debit']); // Credit: Increases what company owes, Debit: Decreases it
            $table->decimal('amount', 15, 2);
            $table->decimal('running_balance', 15, 2)->default(0);
            
            $table->string('category'); // installation_fee, profit_share, cash_collection, advance_payment, payout
            $table->string('description')->nullable();
            
            $table->timestamp('transaction_date');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendor_ledgers');
    }
};
