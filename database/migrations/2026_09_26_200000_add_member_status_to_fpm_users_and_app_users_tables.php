<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// member_status was added manually (not via migration) on local when the
// login-block feature was first built — this migration replays the same
// column on any environment that doesn't already have it, so
// Allowed_to_vote (which is placed AFTER member_status) doesn't fail.
// Guarded with hasColumn() since local already has it.
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('fpm_users', 'member_status')) {
            Schema::table('fpm_users', function (Blueprint $table) {
                $table->boolean('member_status')->default(1);
            });
        }

        if (!Schema::hasColumn('app_users', 'member_status')) {
            Schema::table('app_users', function (Blueprint $table) {
                $table->boolean('member_status')->default(1);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('fpm_users', 'member_status')) {
            Schema::table('fpm_users', function (Blueprint $table) {
                $table->dropColumn('member_status');
            });
        }

        if (Schema::hasColumn('app_users', 'member_status')) {
            Schema::table('app_users', function (Blueprint $table) {
                $table->dropColumn('member_status');
            });
        }
    }
};
