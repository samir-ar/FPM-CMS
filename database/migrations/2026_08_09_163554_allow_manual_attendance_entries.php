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
        Schema::table('event_attendance', function (Blueprint $table) {
            $table->unsignedBigInteger('checked_in_by_app_user_id')->nullable()->change();
            $table->unsignedBigInteger('added_by_admin_id')->nullable()->after('checked_in_by_app_user_id');
            $table->foreign('added_by_admin_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('event_attendance', function (Blueprint $table) {
            $table->dropForeign(['added_by_admin_id']);
            $table->dropColumn('added_by_admin_id');
            $table->unsignedBigInteger('checked_in_by_app_user_id')->nullable(false)->change();
        });
    }
};
