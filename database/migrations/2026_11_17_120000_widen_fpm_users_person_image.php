<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// fpm_users.PersonImage was created as a TEXT column (and on some copies is a
// plain BLOB): both are hard-capped at 65,535 bytes, so every member photo
// larger than ~64KB was silently cut off mid-file (on staging 658 of 40,500
// rows are exactly 65,535 bytes long). MEDIUMBLOB holds 16MB.
//
// This only widens the column: photos that were ALREADY truncated stay
// truncated until the TWH sync sends them again - the sync can now store them
// whole. Existing bytes are kept as they are (TEXT -> MEDIUMBLOB is a
// byte-for-byte conversion). Idempotent: does nothing if already MEDIUMBLOB or
// larger.
return new class extends Migration
{
    public function up()
    {
        $type = DB::table('information_schema.COLUMNS')
            ->where('TABLE_SCHEMA', DB::raw('DATABASE()'))
            ->where('TABLE_NAME', 'fpm_users')
            ->where('COLUMN_NAME', 'PersonImage')
            ->value('DATA_TYPE');

        if (in_array(strtolower((string) $type), ['text', 'tinytext', 'blob', 'tinyblob'], true)) {
            // Rewriting the table re-validates EVERY column of every row, and
            // some member rows carry an invalid '0000-00-00' date_of_birth (from
            // the TWH sync) which strict mode would refuse mid-ALTER. Relax the
            // check for this one session only, then put it back.
            $previousMode = DB::selectOne('SELECT @@SESSION.sql_mode AS m')->m;
            DB::statement("SET SESSION sql_mode = ''");
            try {
                DB::statement('ALTER TABLE fpm_users MODIFY PersonImage MEDIUMBLOB NOT NULL');
            } finally {
                DB::statement('SET SESSION sql_mode = ?', [$previousMode]);
            }
        }
    }

    public function down()
    {
        // Not reversible without cutting photos back to 64KB; intentionally a no-op.
    }
};
