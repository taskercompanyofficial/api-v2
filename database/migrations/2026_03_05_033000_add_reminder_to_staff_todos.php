<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_todos', function (Blueprint $table) {
            $table->time('due_time')->nullable()->after('due_date');
            $table->string('remind_before')->nullable()->after('due_time'); // e.g. "15_min", "30_min", "1_hour", "1_day"
            $table->dateTime('reminder_at')->nullable()->after('remind_before');
            $table->boolean('is_reminded')->default(false)->after('reminder_at');
        });
    }

    public function down(): void
    {
        Schema::table('staff_todos', function (Blueprint $table) {
            $table->dropColumn(['due_time', 'remind_before', 'reminder_at', 'is_reminded']);
        });
    }
};
