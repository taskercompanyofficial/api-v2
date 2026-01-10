<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Change type column from enum to string to support any notification type
        DB::statement("ALTER TABLE notifications MODIFY COLUMN type VARCHAR(50) DEFAULT 'system'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to enum (note: this may fail if there are values not in the enum)
        DB::statement("ALTER TABLE notifications MODIFY COLUMN type ENUM('order', 'promotion', 'system', 'info', 'free_installation') DEFAULT 'system'");
    }
};
