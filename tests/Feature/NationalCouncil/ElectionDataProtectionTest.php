<?php

namespace Tests\Feature\NationalCouncil;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

// Votes are never deleted from the CMS: the "delete all data" / "reset" actions are gone, and an election,
// candidate or poll that already has votes cannot be deleted either (the database would cascade the delete
// into the votes).
class ElectionDataProtectionTest extends CouncilTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        auth('admin')->setUser($this->adminUser());
    }

    public function test_the_delete_all_votes_actions_do_not_exist_any_more()
    {
        $this->assertFalse(Route::has('admin.national-council-poll.clear'));
        $this->assertFalse(Route::has('admin.internal-election-votes.reset'));

        $poll = DB::table('council_national_polls')->insertGetId(['title' => 'T', 'is_published' => 0, 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($this->adminUser(), 'admin');
        $this->get('/admin/national-council-poll/' . $poll . '/clear')->assertNotFound();
        $this->get('/admin/internal-election-vote/reset')->assertNotFound();

        $this->assertFalse(method_exists(\App\Http\Controllers\Admin\NationalCouncilPollController::class, 'clear'));
        $this->assertFalse(method_exists(\App\Http\Controllers\Admin\InternalElectionController::class, 'reset'));
    }

    public function test_an_internal_election_or_candidate_with_votes_cannot_be_deleted()
    {
        $now = now();
        $electionId = DB::table('internal_elections')->insertGetId(['title' => 'T election', 'created_at' => $now, 'updated_at' => $now]);
        $candidateId = DB::table('internal_election_candidates')->insertGetId(['election_id' => $electionId]);
        $userId = $this->makeAppUser('T voter', '99900000099');
        DB::table('internal_election_votes')->insert(['user_id' => $userId, 'candidate_id' => $candidateId, 'internal_election_id' => $electionId, 'rank' => 1, 'weight' => 1, 'voted_at' => $now]);

        $this->actingAs($this->adminUser(), 'admin');

        // the candidate with a vote stays, and so does his vote
        $this->from('/back')->delete(route('admin.internal-election-candidates.destroy', $candidateId, false))->assertSessionHasErrors();
        $this->assertTrue(DB::table('internal_election_candidates')->where('id', $candidateId)->exists());
        $this->assertSame(1, DB::table('internal_election_votes')->where('candidate_id', $candidateId)->count());

        // the election with a vote stays too
        $this->from('/back')->delete(route('admin.internal-election.destroy', $electionId, false))->assertSessionHasErrors();
        $this->assertTrue(DB::table('internal_elections')->where('id', $electionId)->exists());
        $this->assertSame(1, DB::table('internal_election_votes')->where('internal_election_id', $electionId)->count());

        // an election nobody has voted in can still be removed (a mistake made while setting it up)
        $empty = DB::table('internal_elections')->insertGetId(['title' => 'T empty election', 'created_at' => $now, 'updated_at' => $now]);
        $this->from('/back')->delete(route('admin.internal-election.destroy', $empty, false))->assertSessionHasNoErrors();
        $this->assertFalse(DB::table('internal_elections')->where('id', $empty)->exists());
    }

    public function test_a_poll_with_answers_cannot_be_deleted()
    {
        $now = now();
        $pollId = DB::table('polls')->insertGetId(['show' => 1, 'created_at' => $now, 'updated_at' => $now]);
        $optionId = DB::table('polls_options')->insertGetId(['poll_id' => $pollId, 'option' => '{}', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('guest_poll_votes')->insert(['device_id' => 'T-device', 'poll_id' => $pollId, 'option_id' => $optionId, 'created_at' => $now, 'updated_at' => $now]);

        $this->actingAs($this->adminUser(), 'admin');
        $this->from('/back')->delete(route('admin.polls.destroy', $pollId, false))->assertSessionHasErrors();

        $this->assertTrue(DB::table('polls')->where('id', $pollId)->exists());
        $this->assertSame(1, DB::table('guest_poll_votes')->where('poll_id', $pollId)->count(), 'the answer is still there');
    }
}
