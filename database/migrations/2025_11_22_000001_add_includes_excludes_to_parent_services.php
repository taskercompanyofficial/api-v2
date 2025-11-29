<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('parent_services', function (Blueprint $table) {
            if (!Schema::hasColumn('parent_services', 'includes')) {
                $table->json('includes')->nullable();
            }
            if (!Schema::hasColumn('parent_services', 'excludes')) {
                $table->json('excludes')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('parent_services', function (Blueprint $table) {
            if (Schema::hasColumn('parent_services', 'includes')) {
                $table->dropColumn('includes');
            }
            if (Schema::hasColumn('parent_services', 'excludes')) {
                $table->dropColumn('excludes');
            }
        });
    }
};