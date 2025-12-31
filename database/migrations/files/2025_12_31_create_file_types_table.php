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
        Schema::create('file_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->string('description')->nullable();
            $table->integer('max_file_size')->default(5242880); // 5MB in bytes
            $table->json('mime_types')->nullable(); // Array of allowed MIME types
            $table->json('extensions')->nullable(); // Array of allowed extensions
            $table->string('icon')->nullable(); // Icon class or path
            $table->string('color')->nullable(); // Color code for UI
            $table->boolean('is_image')->default(false);
            $table->boolean('is_document')->default(false);
            $table->boolean('is_video')->default(false);
            $table->boolean('is_audio')->default(false);
            $table->boolean('status')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('file_types');
    }
};
