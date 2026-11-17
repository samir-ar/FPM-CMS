<?php

namespace Tests\Feature\NationalCouncil;

use App\Http\Controllers\Api\V2\ApiController;
use App\Http\Repositories\V2\ApiRepository;
use App\V2\AppUser;
use App\V2\CouncilNationalPollVote;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CouncilVotingTest extends CouncilTestCase
{
    private $poll;
    private $otherPoll;
    private $q1;
    private $q2;
    private $qOther;
    private $a1;
    private $a2;
    private $aOther;
    private $user;
    private $dupAccount;
    private $user2;

    protected function setUp(): void
    {
        parent::setUp();

        $now = now();
        $this->user = $this->makeAppUser('T One', '99900000001');
        $this->dupAccount = $this->makeAppUser('T One second account', '99900000001');
        $this->user2 = $this->makeAppUser('T Two', '99900000002');

        $this->poll = DB::table('council_national_polls')->insertGetId(['title' => 'T poll', 'is_published' => 1, 'created_at' => $now, 'updated_at' => $now]);
        $this->otherPoll = DB::table('council_national_polls')->insertGetId(['title' => 'T other poll', 'is_published' => 1, 'created_at' => $now, 'updated_at' => $now]);
        $this->q1 = DB::table('council_national_poll_questions')->insertGetId(['poll_id' => $this->poll, 'question' => 'Q1', 'created_at' => $now, 'updated_at' => $now]);
        $this->q2 = DB::table('council_national_poll_questions')->insertGetId(['poll_id' => $this->poll, 'question' => 'Q2', 'created_at' => $now, 'updated_at' => $now]);
        $this->qOther = DB::table('council_national_poll_questions')->insertGetId(['poll_id' => $this->otherPoll, 'question' => 'QO', 'created_at' => $now, 'updated_at' => $now]);
        $this->a1 = DB::table('answer_council_national_polls')->insertGetId(['question_id' => $this->q1, 'answer' => 'A1', 'created_at' => $now, 'updated_at' => $now]);
        $this->a2 = DB::table('answer_council_national_polls')->insertGetId(['question_id' => $this->q2, 'answer' => 'A2', 'created_at' => $now, 'updated_at' => $now]);
        $this->aOther = DB::table('answer_council_national_polls')->insertGetId(['question_id' => $this->qOther, 'answer' => 'AO', 'created_at' => $now, 'updated_at' => $now]);

        // both members permitted only in $this->poll; member 1 weighs 7, member 2 weighs 3
        DB::table('council_national_poll_permissions')->insert(['poll_id' => $this->poll, 'member_id' => '99900000001', 'vote_weight' => 7, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('council_national_poll_permissions')->insert(['poll_id' => $this->poll, 'member_id' => '99900000002', 'vote_weight' => 3, 'created_at' => $now, 'updated_at' => $now]);
    }

    private function vote(int $userId, array $payload): array
    {
        $req = Request::create('/api/v2/national-council-poll-vote', 'POST', $payload);
        $req->merge(['user' => AppUser::find($userId)]);
        $res = app(ApiController::class)->voteToNationalPoll($req, app(ApiRepository::class));

        return [$res->getStatusCode(), json_decode($res->getContent(), true)];
    }

    private function votes(int $pollId): int
    {
        return DB::table('council_national_poll_votes')->where('poll_id', $pollId)->count();
    }

    private function ok(array $body): bool
    {
        return ($body['message'] ?? '') === 'لقد تم التصويت بنجاح';
    }

    public function test_voting_a_poll_the_member_is_not_in_is_refused()
    {
        [, $body] = $this->vote($this->user, ['poll_id' => $this->otherPoll, 'answers' => [['question_id' => $this->qOther, 'answer_id' => $this->aOther]]]);

        $this->assertFalse($this->ok($body));
        $this->assertSame(0, $this->votes($this->otherPoll));
    }

    public function test_an_answer_belonging_to_another_question_is_refused()
    {
        $this->vote($this->user, ['poll_id' => $this->poll, 'answers' => [['question_id' => $this->q1, 'answer_id' => $this->a2]]]);

        $this->assertSame(0, $this->votes($this->poll));
    }

    public function test_a_question_from_another_poll_is_refused()
    {
        $this->vote($this->user, ['poll_id' => $this->poll, 'answers' => [['question_id' => $this->qOther, 'answer_id' => $this->aOther]]]);

        $this->assertSame(0, $this->votes($this->poll));
    }

    public function test_the_same_question_answered_twice_is_refused()
    {
        $this->vote($this->user, ['poll_id' => $this->poll, 'answers' => [
            ['question_id' => $this->q1, 'answer_id' => $this->a1],
            ['question_id' => $this->q1, 'answer_id' => $this->a1],
        ]]);

        $this->assertSame(0, $this->votes($this->poll));
    }

    public function test_a_valid_vote_is_stored_with_the_servers_weight_member_and_device()
    {
        [, $body] = $this->vote($this->user, ['poll_id' => $this->poll, 'device_id' => 'dev-abc', 'answers' => [
            ['question_id' => $this->q1, 'answer_id' => $this->a1],
            ['question_id' => $this->q2, 'answer_id' => $this->a2],
        ]]);

        $this->assertTrue($this->ok($body));
        $rows = DB::table('council_national_poll_votes')->where('poll_id', $this->poll)->get();
        $this->assertCount(2, $rows);
        foreach ($rows as $r) {
            $this->assertEquals(7, $r->weight);
            $this->assertSame('99900000001', $r->member_id);
            $this->assertSame('dev-abc', $r->device_id);
            $this->assertNotNull($r->voted_at);
        }
    }

    public function test_the_weight_cannot_be_chosen_by_the_client()
    {
        $this->vote($this->user, ['poll_id' => $this->poll, 'weight' => 999, 'answers' => [['question_id' => $this->q1, 'answer_id' => $this->a1]]]);

        $this->assertEquals(7, DB::table('council_national_poll_votes')->where('poll_id', $this->poll)->value('weight'));
    }

    public function test_the_same_account_cannot_vote_twice()
    {
        $payload = ['poll_id' => $this->poll, 'answers' => [['question_id' => $this->q1, 'answer_id' => $this->a1]]];
        $this->vote($this->user, $payload);
        $this->vote($this->user, $payload);

        $this->assertSame(1, $this->votes($this->poll));
    }

    public function test_a_second_app_account_of_the_same_member_cannot_vote_again()
    {
        $payload = ['poll_id' => $this->poll, 'answers' => [['question_id' => $this->q1, 'answer_id' => $this->a1]]];
        $this->vote($this->user, $payload);
        $this->vote($this->dupAccount, $payload);

        $this->assertSame(1, $this->votes($this->poll));
    }

    public function test_the_database_unique_key_blocks_a_raced_duplicate_insert()
    {
        CouncilNationalPollVote::create(['user_id' => $this->user, 'member_id' => '99900000001', 'poll_id' => $this->poll, 'question_id' => $this->q1, 'answer_id' => $this->a1, 'weight' => 7]);

        $this->expectException(QueryException::class);
        CouncilNationalPollVote::create(['user_id' => $this->dupAccount, 'member_id' => '99900000001', 'poll_id' => $this->poll, 'question_id' => $this->q1, 'answer_id' => $this->a1, 'weight' => 7]);
    }

    public function test_a_different_member_can_still_vote()
    {
        $this->vote($this->user, ['poll_id' => $this->poll, 'answers' => [['question_id' => $this->q1, 'answer_id' => $this->a1]]]);
        [, $body] = $this->vote($this->user2, ['poll_id' => $this->poll, 'answers' => [['question_id' => $this->q1, 'answer_id' => $this->a1]]]);

        $this->assertTrue($this->ok($body));
        $this->assertEquals(3, DB::table('council_national_poll_votes')->where('member_id', '99900000002')->value('weight'));
    }

    public function test_an_old_app_build_is_refused_when_a_minimum_build_is_set()
    {
        DB::table('settings')->update(['national_council_min_build' => 999999999]);

        [, $body] = $this->vote($this->user2, ['poll_id' => $this->poll, 'build_number' => 5, 'answers' => [['question_id' => $this->q1, 'answer_id' => $this->a1]]]);

        $this->assertStringContainsString('app_update_required', json_encode($body));
        $this->assertSame(0, $this->votes($this->poll));
    }
}
