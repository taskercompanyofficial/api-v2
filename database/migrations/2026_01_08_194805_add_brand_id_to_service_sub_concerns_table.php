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
        Schema::table('service_sub_concerns', function (Blueprint $table) {
            $table->unsignedBigInteger('authorized_brand_id')->nullable()->after('service_concern_id');
            $table->foreign('authorized_brand_id')->references('id')->on('authorized_brands')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('service_sub_concerns', function (Blueprint $table) {
            $table->dropForeign(['authorized_brand_id']);
            $table->dropColumn('authorized_brand_id');
        });
    }
};
