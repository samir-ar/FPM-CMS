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

        // --- B: both accounts of the member hold an ELECTION vote (different elections): unclear, must be skipped
        $e2 = DB::table('internal_elections')->insertGetId(['title' => 'T dedupe 2', 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
        $cand2 = DB::table('internal_election_candidates')->insertGetId(['first_name' => 'T', 'father_name' => 'C', 'family_name' => 'E', 'election_id' => $e2]);
        $b1 = $this->makeAppUser('T B one', '5590000002', ['verified' => 1]);
        $b2 = $this->makeAppUser('T B two', '5590000002', ['verified' => 1]);
        DB::table('internal_election_votes')->insert(['user_id' => $b1, 'candidate_id' => $c, 'rank' => 1, 'internal_election_id' => $e, 'weight' => 1, 'voted_at' => $now]);
        DB::table('internal_election_votes')->insert(['user_id' => $b2, 'candidate_id' => $cand2, 'rank' => 1, 'internal_election_id' => $e2, 'weight' => 1, 'voted_at' => $now]);

        // --- C: nobody holds a vote: the account really used most recently wins, even if the other is "verified"
        $c1 = $this->makeAppUser('T C used lately', '5590000003', ['verified' => 0]);
        $c2 = $this->makeAppUser('T C verified but never used', '5590000003', ['verified' => 1]);
        DB::table('user_activity_logs')->insert(['app_user_id' => $c1, 'activity_date' => now()->toDateString(), 'created_at' => now()]);

        // --- D: no votes and no activity anywhere: the old rules apply (verified account wins)
        $d1 = $this->makeAppUser('T D unverified', '5590000004', ['verified' => 0]);
        $d2 = $this->makeAppUser('T D verified', '5590000004', ['verified' => 1]);

        // --- E: both accounts only hold OLD OPINION-POLL answers: these do not protect anyone, activity decides
        $pollId = DB::table('polls')->insertGetId(['question' => json_encode(['ar' => 'T', 'en' => 'T']), 'show' => 1, 'show_for_guest' => 0, 'expiry_date' => now()->subYears(3), 'created_at' => $now, 'updated_at' => $now]);
        $opt = DB::table('polls_options')->insertGetId(['poll_id' => $pollId, 'option' => json_encode(['ar' => 'A', 'en' => 'A']), 'created_at' => $now, 'updated_at' => $now]);
        $p1 = $this->makeAppUser('T E old poll acct', '5590000005', ['verified' => 1]);
        $p2 = $this->makeAppUser('T E active acct', '5590000005', ['verified' => 1]);
        $this->pollAnswer($p1, $pollId, $opt);
        $this->pollAnswer($p2, $pollId, $opt);
        DB::table('user_activity_logs')->insert(['app_user_id' => $p2, 'activity_date' => now()->toDateString(), 'created_at' => now()]);

        // a dry run writes nothing
        Artisan::call('app-users:dedupe');
        foreach ([$voter, $shiny, $b1, $b2, $c1, $c2, $d1, $d2, $p1, $p2] as $id) {
            $this->assertTrue($this->active($id), 'a dry run must not delete anything');
        }

        Artisan::call('app-users:dedupe', ['--commit' => true]);
        $output = Artisan::output();

        $this->assertTrue($this->active($voter), 'A: the account holding the vote stays');
        $this->assertFalse($this->active($shiny), 'A: the other account is soft-deleted');
        $this->assertTrue($this->active($b1) && $this->active($b2), 'B: several vote holders - group untouched');
        $this->assertStringContainsString('SKIPPED member_id=5590000002', $output);
        $this->assertTrue($this->active($c1), 'C: the account used most recently stays');
        $this->assertFalse($this->active($c2), 'C: the verified-but-never-used one is soft-deleted');
        $this->assertTrue($this->active($d2), 'D: with no votes and no activity the verified account stays');
        $this->assertFalse($this->active($d1), 'D: the unverified one is soft-deleted');
        $this->assertTrue($this->active($p2), 'E: opinion-poll answers do not protect; the active account stays');
        $this->assertFalse($this->active($p1), 'E: the other account is soft-deleted');
        // soft delete only: the rows are still there
        $this->assertSame(1, DB::table('app_users')->where('id', $shiny)->whereNotNull('deleted_at')->count());
        $this->assertSame(1, DB::table('internal_election_votes')->where('user_id', $voter)->count());
    }
}
