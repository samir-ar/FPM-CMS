<?php

namespace Tests\Feature\NationalCouncil;

use App\Http\Controllers\Api\V2\ApiController;
use App\V2\AppUser;
use Illuminate\Support\Facades\DB;

// An internal-election vote is saved against an app account. The guard must also look at the MEMBER, so two
// accounts carrying the same member id (with or without a leading zero) cannot each vote once.
class InternalElectionMemberVoteTest extends CouncilTestCase
{
    private function hasVoted(int $userId, int $electionId): bool
    {
        $m = new \ReflectionMethod(ApiController::class, 'memberHasVotedInElection');
        $m->setAccessible(true);

        return $m->invoke(app(ApiController::class), AppUser::withTrashed()->find($userId), $electionId);
    }

    public function test_a_vote_by_one_account_blocks_every_account_of_the_same_member(): void
    {
        $now = now();
        $electionId = DB::table('internal_elections')->insertGetId(['title' => 'T election', 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
        $otherElection = DB::table('internal_elections')->insertGetId(['title' => 'T other election', 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
        $cand = DB::table('internal_election_candidates')->insertGetId(['first_name' => 'T', 'father_name' => 'C', 'family_name' => 'Cand', 'election_id' => $electionId]);

        $voter = $this->makeAppUser('T voter', '5550000001');
        $secondAccount = $this->makeAppUser('T voter second account', '5550000001');
        $zeroAccount = $this->makeAppUser('T voter zero-padded account', '05550000001');
        $stranger = $this->makeAppUser('T stranger', '5550000002');

        $this->assertFalse($this->hasVoted($voter, $electionId), 'nobody has voted yet');

        DB::table('internal_election_votes')->insert(['user_id' => $voter, 'candidate_id' => $cand, 'rank' => 1, 'internal_election_id' => $electionId, 'weight' => 1, 'voted_at' => $now]);

        $this->assertTrue($this->hasVoted($voter, $electionId), 'the account that voted');
        $this->assertTrue($this->hasVoted($secondAccount, $electionId), 'another account of the same member id');
        $this->assertTrue($this->hasVoted($zeroAccount, $electionId), 'the same member id written with a leading zero');
        $this->assertFalse($this->hasVoted($stranger, $electionId), 'a different member is not blocked');
        $this->assertFalse($this->hasVoted($secondAccount, $otherElection), 'a vote in one election does not block another election');
    }
}
