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
        Schema::table('work_order_parts', function (Blueprint $table) {
            // Payment proof document (REQUIRED for payable parts before dispatch)
            $table->string('payment_proof_path')->nullable()->after('notes');
            $table->timestamp('payment_proof_uploaded_at')->nullable()->after('payment_proof_path');
            
            // Gas pass slip document (optional when receiving parts)
            $table->string('gas_pass_slip_path')->nullable()->after('payment_proof_uploaded_at');
            $table->timestamp('gas_pass_slip_uploaded_at')->nullable()->after('gas_pass_slip_path');
            
            // Return slip document (optional when returning faulty parts)
            $table->string('return_slip_path')->nullable()->after('gas_pass_slip_uploaded_at');
            $table->timestamp('return_slip_uploaded_at')->nullable()->after('return_slip_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('work_order_parts', function (Blueprint $table) {
            $table->dropColumn([
                'payment_proof_path',
                'payment_proof_uploaded_at',
                'gas_pass_slip_path',
                'gas_pass_slip_uploaded_at',
                'return_slip_path',
                'return_slip_uploaded_at',
            ]);
        });
    }
};
