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
        Schema::table('notifications', function (Blueprint $table) {
            // Remove old columns if they exist
            if (Schema::hasColumn('notifications', 'customer_id')) {
                $table->dropForeign(['customer_id']);
                $table->dropColumn('customer_id');
            }
            if (Schema::hasColumn('notifications', 'order_id')) {
                $table->dropForeign(['order_id']);
                $table->dropColumn('order_id');
            }

            // Add new universal columns
            $table->unsignedBigInteger('user_id')->after('id');
            $table->string('user_type')->after('user_id'); // e.g., 'App\Models\Staff', 'App\Models\Customer'
            $table->json('data')->nullable()->after('type'); // For storing additional data
            
            // Add index for polymorphic relationship
            $table->index(['user_id', 'user_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            // Remove new columns
            $table->dropIndex(['user_id', 'user_type']);
            $table->dropColumn(['user_id', 'user_type', 'data']);
            
            // Restore old columns
            $table->unsignedBigInteger('customer_id')->nullable()->after('id');
            $table->unsignedBigInteger('order_id')->nullable();
            
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
        });
    }
};
