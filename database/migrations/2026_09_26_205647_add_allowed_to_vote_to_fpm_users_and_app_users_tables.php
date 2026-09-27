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
        Schema::table('fpm_users', function (Blueprint $table) {
            $table->tinyInteger('Allowed_to_vote')->default(0)->after('member_status');
        });

        Schema::table('app_users', function (Blueprint $table) {
            $table->tinyInteger('Allowed_to_vote')->default(0)->after('member_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fpm_users', function (Blueprint $table) {
            $table->dropColumn('Allowed_to_vote');
        });

        Schema::table('app_users', function (Blueprint $table) {
            $table->dropColumn('Allowed_to_vote');
        });
    }
};
