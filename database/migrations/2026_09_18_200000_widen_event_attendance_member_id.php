<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * member_id was created as unsignedInteger (max ~4.29 billion), but
     * fpm_users.MemberId is a bigint and real membership numbers can exceed
     * that (e.g. 90004300142) — inserting one throws "Numeric value out of
     * range" (SQLSTATE 22003). Widen to match fpm_users.MemberId's type.
     * Raw SQL (not ->change()) to avoid a doctrine/dbal dependency.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE event_attendance MODIFY member_id BIGINT UNSIGNED NOT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE event_attendance MODIFY member_id INT UNSIGNED NOT NULL');
    }
};
