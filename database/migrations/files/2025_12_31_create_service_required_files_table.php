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
        Schema::create('service_required_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_service_id')->constrained('parent_services')->onDelete('cascade');
            $table->foreignId('file_type_id')->constrained('file_types')->onDelete('cascade');
            $table->boolean('is_required')->default(false);
            $table->timestamps();

            // Ensure unique combination of parent_service_id and file_type_id
            $table->unique(['parent_service_id', 'file_type_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_required_files');
    }
};
