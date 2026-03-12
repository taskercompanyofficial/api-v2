<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('vendor_staff', function (Blueprint $table) {
            if (!Schema::hasColumn('vendor_staff', 'experience')) {
                $table->string('experience')->nullable()->after('cnic');
            }
            if (!Schema::hasColumn('vendor_staff', 'image')) {
                $table->string('image')->nullable()->after('name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('vendor_staff', function (Blueprint $table) {
            $table->dropColumn(['experience', 'image']);
        });
    }
};
