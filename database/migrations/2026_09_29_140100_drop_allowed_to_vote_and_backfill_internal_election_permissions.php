<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// Retires the global Allowed_to_vote gate in favor of the new per-election
// internal_election_permissions allowlist. Before dropping the column,
// backfill one permission row per (currently-active election x currently
// Allowed_to_vote=1 member) so nobody who can vote today loses that
// ability the moment this ships.
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            INSERT INTO internal_election_permissions (election_id, member_id, created_at, updated_at)
            SELECT ie.id, fu.MemberId, NOW(), NOW()
            FROM internal_elections ie
            CROSS JOIN fpm_users fu
            WHERE ie.is_active = 1 AND fu.Allowed_to_vote = 1
        ");

        if (Schema::hasColumn('fpm_users', 'Allowed_to_vote')) {
            Schema::table('fpm_users', function (Blueprint $table) {
                $table->dropColumn('Allowed_to_vote');
            });
        }

        if (Schema::hasColumn('app_users', 'Allowed_to_vote')) {
            Schema::table('app_users', function (Blueprint $table) {
                $table->dropColumn('Allowed_to_vote');
            });
        }
    }

    public function down(): void
    {
        // Schema-only rollback — the column is being retired for good, so
        // this does not attempt to reverse-populate prior per-member values
        // (internal_election_permissions stays the source of truth going
        // forward regardless of whether this migration is rolled back).
        if (!Schema::hasColumn('fpm_users', 'Allowed_to_vote')) {
            Schema::table('fpm_users', function (Blueprint $table) {
                $table->tinyInteger('Allowed_to_vote')->default(0)->after('member_status');
            });
        }

        if (!Schema::hasColumn('app_users', 'Allowed_to_vote')) {
            Schema::table('app_users', function (Blueprint $table) {
                $table->tinyInteger('Allowed_to_vote')->default(0)->after('member_status');
            });
        }
    }
};
