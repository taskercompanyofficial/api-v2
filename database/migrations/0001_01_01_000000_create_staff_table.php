<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('staff', function (Blueprint $table) {
            $table->id();
          $table->foreignId('created_by')->nullable()->constrained('staff')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('staff')->onDelete('set null');
            $table->string('code')->unique();
            $table->string('slug')->unique();
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');
            $table->string('cnic')->unique();
            $table->date('dob');
            $table->string('gender');
            $table->string('email')->nullable();
            $table->string('phone');
            $table->string('profile_image');
            $table->string('cnic_front_image');
            $table->string('cnic_back_image');
            $table->text('permanent_address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('postal_code')->nullable();
            $table->date('joining_date')->nullable();
            $table->unsignedBigInteger('role_id')->nullable();
            $table->unsignedBigInteger('status_id')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('has_access_in_crm')->default(false);
            $table->string('crm_login_email')->nullable();
            $table->string('crm_login_password')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status','designation']);

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff');
    }
};