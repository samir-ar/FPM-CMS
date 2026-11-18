<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// One ACTIVE app account per member, enforced by the database itself.
//
// `active_member_key` is computed by MySQL from the row: the member id with leading zeros stripped while the account
// is not deleted, otherwise NULL. A unique index on it means two active accounts of one member (0100100129 and
// 100100129 count as one) can never exist, whatever code path tries to create them. Soft-deleted accounts and
// accounts without a member id have a NULL key, and a unique index allows any number of NULLs.
//
// VIRTUAL (not STORED) on purpose: a stored column makes MySQL rebuild the whole table, which fails on old rows that
// hold a '0000-00-00' date; a virtual column with an index is added in place, without touching the rows.
//
// It refuses to run while duplicates still exist (it never deletes or hides anything by itself): run
//   php artisan app-users:dedupe            (dry run, shows what it would do)
//   php artisan app-users:dedupe --commit
// first, then migrate again.
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('app_users', 'active_member_key')) {
            return;
        }

        $dupes = DB::select("SELECT TRIM(LEADING '0' FROM member_id) AS k, COUNT(*) AS c
            FROM app_users
            WHERE deleted_at IS NULL AND member_id IS NOT NULL AND TRIM(LEADING '0' FROM member_id) <> ''
            GROUP BY k HAVING c > 1 LIMIT 5");

        if ($dupes) {
            $sample = implode(', ', array_map(fn ($d) => $d->k . ' (' . $d->c . ' accounts)', $dupes));
            throw new RuntimeException("Some members still have more than one active app account (e.g. $sample). "
                . 'Run "php artisan app-users:dedupe" (dry run) and then "php artisan app-users:dedupe --commit" first, then migrate again.');
        }

        // Adding the index makes MySQL re-check every old row, and some hold a '0000-00-00' date that strict mode
        // refuses: relax the SQL mode for this one statement on this connection only, then put it back.
        $mode = DB::selectOne('SELECT @@SESSION.sql_mode AS m')->m;
        DB::statement("SET SESSION sql_mode = 'NO_ENGINE_SUBSTITUTION'");
        try {
        DB::statement("ALTER TABLE app_users
            ADD COLUMN active_member_key VARCHAR(32)
                GENERATED ALWAYS AS (
                    CASE WHEN deleted_at IS NULL AND member_id IS NOT NULL AND TRIM(LEADING '0' FROM member_id) <> ''
                         THEN TRIM(LEADING '0' FROM member_id) ELSE NULL END
                ) VIRTUAL,
            ADD UNIQUE INDEX app_users_one_active_account_per_member (active_member_key)");
        } finally {
            DB::statement('SET SESSION sql_mode = ?', [$mode]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('app_users', 'active_member_key')) {
            DB::statement('ALTER TABLE app_users DROP INDEX app_users_one_active_account_per_member, DROP COLUMN active_member_key');
        }
    }
};
