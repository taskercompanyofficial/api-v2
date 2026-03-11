<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('vendor_staff', function (Blueprint $table) {
            $table->string('password')->nullable()->after('phone');
            $table->string('email')->nullable()->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('vendor_staff', function (Blueprint $table) {
            $table->dropColumn(['password', 'email']);
        });
    }
};
