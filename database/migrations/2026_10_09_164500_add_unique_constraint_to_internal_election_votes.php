<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The only thing preventing a double-vote before this was an app-level
 * check in ApiController::canIVote() - not atomic with the later
 * attach() insert, so two near-simultaneous requests (double-tap, a
 * network retry, two sessions) could both pass the check and both
 * insert a vote row. This adds a hard database-level guarantee: one
 * vote per (user, election), enforced by MySQL itself regardless of
 * any application-level race.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('internal_election_votes', function (Blueprint $table) {
            $table->unique(['user_id', 'internal_election_id'], 'ie_votes_user_election_unique');
        });
    }

    public function down(): void
    {
        Schema::table('internal_election_votes', function (Blueprint $table) {
            $table->dropUnique('ie_votes_user_election_unique');
        });
    }
};
