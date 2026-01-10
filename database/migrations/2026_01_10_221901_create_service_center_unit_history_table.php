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
        Schema::create('service_center_unit_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_center_unit_id')->constrained('service_center_units')->onDelete('cascade');
            $table->string('status')->nullable();
            $table->string('action'); // 'created', 'picked_up', 'received', 'diagnosis_complete', etc.
            $table->text('notes')->nullable();
            $table->foreignId('performed_by')->nullable()->constrained('staff')->nullOnDelete();
            $table->dateTime('performed_at');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_center_unit_history');
    }
};
