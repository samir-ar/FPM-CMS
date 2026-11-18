<?php

namespace Tests\Feature\NationalCouncil;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

// app-users:dedupe soft-deletes the extra accounts of a member. The account that HOLDS the member's votes must be the
// one that stays, whatever else makes another account look "better" (verified, push token, newer).
class DedupeKeepsVoteHolderTest extends CouncilTestCase
{
    private function active(int $id): bool
    {
        return DB::table('app_users')->where('id', $id)->whereNull('deleted_at')->exists();
    }

    private function pollAnswer(int $userId, int $pollId, int $optionId): void
    {
        DB::table('users_polls')->insert(['app_user_id' => $userId, 'poll_id' => $pollId, 'option_id' => $optionId, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_the_account_holding_the_vote_is_kept_and_unclear_groups_are_left_alone(): void
    {
        $now = now();

        // --- A: an old, unverified account holds the election vote; the newer verified one with a push token does not
        $e = DB::table('internal_elections')->insertGetId(['title' => 'T dedupe', 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
        $c = DB::table('internal_election_candidates')->insertGetId(['first_name' => 'T', 'father_name' => 'C', 'family_name' => 'D', 'election_id' => $e]);
        $voter = $this->makeAppUser('T A voter acct', '5590000001', ['verified' => 0]);
        $shiny = $this->makeAppUser('T A newer acct', '5590000001', ['verified' => 1, 'player_id' => 'T-push-A']);
        DB::table('internal_election_votes')->insert(['user_id' => $voter, 'candidate_id' => $c, 'rank' => 1, 'internal_election_id' => $e, 'weight' => 1, 'voted_at' => $now]);

        // --- B: both accounts of the member hold a poll answer: unclear, must be skipped
        $pollId = DB::table('polls')->insertGetId(['question' => json_encode(['ar' => 'T', 'en' => 'T']), 'show' => 1, 'show_for_guest' => 0, 'expiry_date' => now()->addDay(), 'created_at' => $now, 'updated_at' => $now]);
        $opt = DB::table('polls_options')->insertGetId(['poll_id' => $pollId, 'option' => json_encode(['ar' => 'A', 'en' => 'A']), 'created_at' => $now, 'updated_at' => $now]);
        $b1 = $this->makeAppUser('T B one', '5590000002', ['verified' => 1]);
        $b2 = $this->makeAppUser('T B two', '5590000002', ['verified' => 1]);
        $this->pollAnswer($b1, $pollId, $opt);
        $this->pollAnswer($b2, $pollId, $opt);

        // --- C: nobody holds a vote: the old rules apply (verified account wins)
        $c1 = $this->makeAppUser('T C unverified', '5590000003', ['verified' => 0]);
        $c2 = $this->makeAppUser('T C verified', '5590000003', ['verified' => 1]);

        // a dry run writes nothing
        Artisan::call('app-users:dedupe');
        foreach ([$voter, $shiny, $b1, $b2, $c1, $c2] as $id) {
            $this->assertTrue($this->active($id), 'a dry run must not delete anything');
        }

        Artisan::call('app-users:dedupe', ['--commit' => true]);
        $output = Artisan::output();

        $this->assertTrue($this->active($voter), 'A: the account holding the vote stays');
        $this->assertFalse($this->active($shiny), 'A: the other account is soft-deleted');
        $this->assertTrue($this->active($b1) && $this->active($b2), 'B: several vote holders - group untouched');
        $this->assertStringContainsString('SKIPPED member_id=5590000002', $output);
        $this->assertTrue($this->active($c2), 'C: the verified account stays');
        $this->assertFalse($this->active($c1), 'C: the unverified one is soft-deleted');
        // soft delete only: the rows are still there
        $this->assertSame(1, DB::table('app_users')->where('id', $shiny)->whereNotNull('deleted_at')->count());
        $this->assertSame(1, DB::table('internal_election_votes')->where('user_id', $voter)->count());
    }
}
