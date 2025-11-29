<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('staff_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('staff_id');
            $table->string('type');
            $table->string('file_path');
            $table->date('issued_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->boolean('verified')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->foreign('staff_id')->references('id')->on('staff')->cascadeOnDelete();
            $table->index(['type','expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_documents');
    }
};