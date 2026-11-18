<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

// "One vote per MEMBER" enforced by the database itself (not only by code), for the internal election and for
// regular polls. A vote is saved against an app account, and one member can hold several accounts, so each vote now
// also stores the member id (leading zeros stripped) in `member_key`, with a unique rule per election / poll.
//
// Existing votes are never deleted. They are back-filled with their member key; where one member already has more
// than one vote in the same election/poll, the earliest keeps its key and the later ones get NULL (a unique rule
// allows many NULLs), so history stays intact and the rule can be added. The number of such rows is written to the log.
return new class extends Migration
{
    private const TABLES = [
        // table => [scope column, unique index name]
        'internal_election_votes' => ['internal_election_id', 'ie_votes_election_member_unique'],
        'users_polls' => ['poll_id', 'users_polls_poll_member_unique'],
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table => [$scope, $index]) {
            if (!Schema::hasColumn($table, 'member_key')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->string('member_key', 32)->nullable();
                });
            }

            // back-fill from the voter's account
            $uc = $table === 'users_polls' ? 'app_user_id' : 'user_id';
            DB::statement("UPDATE `$table` t JOIN app_users u ON u.id = t.`$uc`
                SET t.member_key = NULLIF(TRIM(LEADING '0' FROM u.member_id), '')
                WHERE t.member_key IS NULL AND u.member_id IS NOT NULL");

            // a member already holding several votes in the same election/poll: keep the earliest, free the rest
            $dupes = DB::selectOne("SELECT COUNT(*) c FROM `$table` t JOIN (
                    SELECT `$scope` sc, member_key mk, MIN(id) keep_id FROM `$table` WHERE member_key IS NOT NULL GROUP BY `$scope`, member_key HAVING COUNT(*) > 1
                ) d ON d.sc = t.`$scope` AND d.mk = t.member_key AND t.id <> d.keep_id")->c;
            if ($dupes > 0) {
                DB::statement("UPDATE `$table` t JOIN (
                        SELECT `$scope` sc, member_key mk, MIN(id) keep_id FROM `$table` WHERE member_key IS NOT NULL GROUP BY `$scope`, member_key HAVING COUNT(*) > 1
                    ) d ON d.sc = t.`$scope` AND d.mk = t.member_key AND t.id <> d.keep_id
                    SET t.member_key = NULL");
            }
            Log::info("one-vote rule: $table - $dupes extra vote row(s) of an already-counted member had their member_key cleared (rows kept)");

            $has = collect(DB::select("SHOW INDEX FROM `$table` WHERE Key_name = ?", [$index]))->isNotEmpty();
            if (!$has) {
                Schema::table($table, function (Blueprint $t) use ($scope, $index) {
                    $t->unique([$scope, 'member_key'], $index);
                });
            }
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table => [$scope, $index]) {
            Schema::table($table, function (Blueprint $t) use ($index) {
                $t->dropUnique($index);
                $t->dropColumn('member_key');
            });
        }
    }
};
