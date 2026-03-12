<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('vendor_staff', function (Blueprint $table) {
            if (!Schema::hasColumn('vendor_staff', 'email')) {
                $table->string('email')->nullable()->after('phone');
            }
        });
    }

    public function down(): void
    {
        Schema::table('vendor_staff', function (Blueprint $table) {
            $table->dropColumn('email');
        });
    }
};
