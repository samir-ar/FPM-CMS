<?php

namespace Tests\Feature\NationalCouncil;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

// Election and poll data is never removed from the database through the CMS:
//  - the "delete all votes / delete all data" actions do not exist;
//  - "Delete" on a council poll, an Internal Election or a regular poll only MARKS it as deleted (deleted_at),
//    hides it in the CMS and the app, and keeps the poll, its voters and every vote;
//  - a candidate who has votes cannot be deleted (the database would cascade the delete into the votes).
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

    public function test_deleting_a_council_poll_only_marks_it_deleted_and_keeps_everything()
    {
        $now = now();
        $poll = DB::table('council_national_polls')->insertGetId(['title' => 'T keep me', 'is_published' => 1, 'created_at' => $now, 'updated_at' => $now]);
        $question = DB::table('council_national_poll_questions')->insertGetId(['poll_id' => $poll, 'question' => 'Q', 'created_at' => $now, 'updated_at' => $now]);
        $answer = DB::table('answer_council_national_polls')->insertGetId(['question_id' => $question, 'answer' => 'A', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('council_national_poll_permissions')->insert(['poll_id' => $poll, 'member_id' => '99900000077', 'vote_weight' => 3, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('council_national_poll_votes')->insert(['user_id' => $this->makeAppUser('T v', '99900000077'), 'member_id' => '99900000077', 'poll_id' => $poll, 'question_id' => $question, 'answer_id' => $answer, 'weight' => 3, 'voted_at' => $now, 'created_at' => $now, 'updated_at' => $now]);
        $this->actingAs($this->adminUser(), 'admin');
        $delete = fn() => $this->from('/back')->delete(route('admin.national-council-poll.destroy', $poll, false));

        // a LIVE poll cannot be deleted by accident
        $delete()->assertSessionHasErrors();
        $this->assertNull(DB::table('council_national_polls')->where('id', $poll)->value('deleted_at'));

        // unpublished: marked as deleted, not removed
        DB::table('council_national_polls')->where('id', $poll)->update(['is_published' => 0]);
        $delete()->assertSessionHasNoErrors();
        $this->assertNotNull(DB::table('council_national_polls')->where('id', $poll)->value('deleted_at'), 'marked as deleted');
        $this->assertTrue(DB::table('council_national_polls')->where('id', $poll)->exists(), 'the row is still in the database');
        $this->assertSame(1, DB::table('council_national_poll_questions')->where('poll_id', $poll)->count());
        $this->assertSame(1, DB::table('answer_council_national_polls')->where('id', $answer)->count());
        $this->assertSame(1, DB::table('council_national_poll_permissions')->where('poll_id', $poll)->count());
        $this->assertSame(1, DB::table('council_national_poll_votes')->where('poll_id', $poll)->count(), 'the vote is kept');

        // ...and hidden from the CMS list
        $ids = collect($this->getJson(route('admin.national-council-poll.index', [], false), ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()->json('data'))->pluck('id');
        $this->assertNotContains($poll, $ids->all());
    }

    public function test_deleting_an_internal_election_only_marks_it_deleted_and_keeps_its_votes()
    {
        $now = now();
        $electionId = DB::table('internal_elections')->insertGetId(['title' => 'T election', 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
        $candidateId = DB::table('internal_election_candidates')->insertGetId(['election_id' => $electionId]);
        $userId = $this->makeAppUser('T voter', '99900000099');
        DB::table('internal_election_votes')->insert(['user_id' => $userId, 'candidate_id' => $candidateId, 'internal_election_id' => $electionId, 'rank' => 1, 'weight' => 1, 'voted_at' => $now]);
        $this->actingAs($this->adminUser(), 'admin');
        $delete = fn() => $this->from('/back')->delete(route('admin.internal-election.destroy', $electionId, false));

        // an ACTIVE election cannot be deleted by accident
        $delete()->assertSessionHasErrors();
        $this->assertNull(DB::table('internal_elections')->where('id', $electionId)->value('deleted_at'));

        // switched off: marked as deleted, with its candidate and its vote untouched
        DB::table('internal_elections')->where('id', $electionId)->update(['is_active' => 0]);
        $delete()->assertSessionHasNoErrors();
        $this->assertNotNull(DB::table('internal_elections')->where('id', $electionId)->value('deleted_at'));
        $this->assertSame(1, DB::table('internal_election_candidates')->where('election_id', $electionId)->count());
        $this->assertSame(1, DB::table('internal_election_votes')->where('internal_election_id', $electionId)->count(), 'the vote is kept');
    }

    public function test_a_candidate_with_votes_cannot_be_deleted()
    {
        $now = now();
        $electionId = DB::table('internal_elections')->insertGetId(['title' => 'T election', 'created_at' => $now, 'updated_at' => $now]);
        $candidateId = DB::table('internal_election_candidates')->insertGetId(['election_id' => $electionId]);
        DB::table('internal_election_votes')->insert(['user_id' => $this->makeAppUser('T voter', '99900000098'), 'candidate_id' => $candidateId, 'internal_election_id' => $electionId, 'rank' => 1, 'weight' => 1, 'voted_at' => $now]);

        $this->actingAs($this->adminUser(), 'admin');
        $this->from('/back')->delete(route('admin.internal-election-candidates.destroy', $candidateId, false))->assertSessionHasErrors();

        $this->assertTrue(DB::table('internal_election_candidates')->where('id', $candidateId)->exists());
        $this->assertSame(1, DB::table('internal_election_votes')->where('candidate_id', $candidateId)->count());
    }

    public function test_deleting_a_regular_poll_only_marks_it_deleted_and_keeps_its_answers()
    {
        $now = now();
        $pollId = DB::table('polls')->insertGetId(['show' => 1, 'created_at' => $now, 'updated_at' => $now]);
        $optionId = DB::table('polls_options')->insertGetId(['poll_id' => $pollId, 'option' => '{}', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('guest_poll_votes')->insert(['device_id' => 'T-device', 'poll_id' => $pollId, 'option_id' => $optionId, 'created_at' => $now, 'updated_at' => $now]);

        $this->actingAs($this->adminUser(), 'admin');
        $this->from('/back')->delete(route('admin.polls.destroy', $pollId, false))->assertSessionHasNoErrors();

        $this->assertNotNull(DB::table('polls')->where('id', $pollId)->value('deleted_at'), 'marked as deleted');
        $this->assertSame(1, DB::table('guest_poll_votes')->where('poll_id', $pollId)->count(), 'the answer is kept');
    }
}
