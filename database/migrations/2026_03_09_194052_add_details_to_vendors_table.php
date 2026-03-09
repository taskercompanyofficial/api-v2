<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->string('slug')->unique()->after('id')->nullable();
            $table->string('first_name')->after('name')->nullable();
            $table->string('middle_name')->after('first_name')->nullable();
            $table->string('last_name')->after('middle_name')->nullable();
            $table->string('father_name')->after('last_name')->nullable();
            $table->string('company_name')->after('father_name')->nullable(); // Shop name
            $table->string('cnic')->after('company_name')->nullable();
            $table->date('dob')->after('cnic')->nullable();
            $table->string('gender')->after('dob')->nullable();
            $table->string('profile_image')->after('gender')->nullable();
            $table->string('cnic_front_image')->after('profile_image')->nullable();
            $table->string('cnic_back_image')->after('cnic_front_image')->nullable();
            $table->text('address')->after('cnic_back_image')->nullable();
            $table->string('city')->after('address')->nullable();
            $table->string('state')->after('city')->nullable();
            $table->string('postal_code')->after('state')->nullable();
            $table->date('joining_date')->after('postal_code')->nullable();
            $table->text('notes')->after('joining_date')->nullable();
            $table->unsignedBigInteger('role_id')->nullable()->after('notes');
            // 'name' column originally could be kept as fallback or deprecated
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->dropColumn([
                'slug',
                'first_name',
                'middle_name',
                'last_name',
                'father_name',
                'company_name',
                'cnic',
                'dob',
                'gender',
                'profile_image',
                'cnic_front_image',
                'cnic_back_image',
                'address',
                'city',
                'state',
                'postal_code',
                'joining_date',
                'notes',
                'role_id'
            ]);
        });
    }
};
