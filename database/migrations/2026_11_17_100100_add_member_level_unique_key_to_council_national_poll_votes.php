<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Until now a National Council vote was only tied to an app account
    // (user_id), with no unique key at all: a double-tap, two parallel
    // requests, or a member holding two app accounts could each record a
    // second vote. This ties every vote to the member (member_id) and makes
    // the database itself refuse a second vote for the same
    // (poll, question, member).
    public function up()
    {
        Schema::table('council_national_poll_votes', function (Blueprint $table) {
            $table->string('member_id', 32)->nullable()->after('user_id');
            $table->string('device_id', 191)->nullable()->after('weight');
            $table->timestamp('voted_at')->nullable()->after('device_id');
        });

        // Backfill existing rows from the account that cast them.
        DB::statement(
            'UPDATE council_national_poll_votes v
             JOIN app_users u ON u.id = v.user_id
             SET v.member_id = u.member_id
             WHERE v.member_id IS NULL'
        );

        // Refuse to add the unique key on top of existing double votes —
        // surface them loudly instead of failing with a cryptic SQL error or
        // silently deleting votes.
        $dups = DB::table('council_national_poll_votes')
            ->select('poll_id', 'question_id', 'member_id', DB::raw('COUNT(*) as c'))
            ->whereNotNull('member_id')
            ->groupBy('poll_id', 'question_id', 'member_id')
            ->having('c', '>', 1)
            ->get();

        if ($dups->isNotEmpty()) {
            throw new RuntimeException(
                'Cannot add the unique vote key: ' . $dups->count() .
                ' (poll, question, member) combination(s) already have more than one vote. ' .
                'Review council_national_poll_votes manually first. First offender: poll ' .
                $dups->first()->poll_id . ', question ' . $dups->first()->question_id .
                ', member ' . $dups->first()->member_id . '.'
            );
        }

        Schema::table('council_national_poll_votes', function (Blueprint $table) {
            $table->unique(['poll_id', 'question_id', 'member_id'], 'cnpv_poll_question_member_unique');
        });
    }

    public function down()
    {
        Schema::table('council_national_poll_votes', function (Blueprint $table) {
            $table->dropUnique('cnpv_poll_question_member_unique');
            $table->dropColumn(['member_id', 'device_id', 'voted_at']);
        });
    }
};
