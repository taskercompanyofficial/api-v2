<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // For MySQL, we need to alter the enum column
        DB::statement("ALTER TABLE notifications MODIFY COLUMN type ENUM('order', 'promotion', 'system', 'free_installation') DEFAULT 'system'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE notifications MODIFY COLUMN type ENUM('order', 'promotion', 'system') DEFAULT 'system'");
    }
};
