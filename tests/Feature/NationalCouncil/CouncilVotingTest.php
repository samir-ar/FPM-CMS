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
        [, $body] = $this->vote($this->user, ['poll_id' => $this->poll, 'device_id' => 'dev-abc', 'device_model' => 'Samsung SM-A525F, Android 13', 'hardware_id' => 'HW-1234abcd', 'answers' => [
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
            $this->assertSame('Samsung SM-A525F, Android 13', $r->device_model, 'the phone make/model is stored with the vote');
            $this->assertSame('HW-1234abcd', $r->hardware_id, 'the phone\'s hardware id is stored with the vote');
            $this->assertNotNull($r->voted_at);
        }
    }

    public function test_the_phone_model_is_optional_and_never_longer_than_the_column()
    {
        // an older app build sends no model: the vote still works, the model stays empty
        [, $old] = $this->vote($this->user2, ['poll_id' => $this->poll, 'answers' => [['question_id' => $this->q1, 'answer_id' => $this->a1]]]);
        $this->assertTrue($this->ok($old));
        $this->assertNull(DB::table('council_national_poll_votes')->where('member_id', '99900000002')->value('device_model'));

        // an over-long value is refused by validation instead of breaking the insert
        [, $long] = $this->vote($this->user, ['poll_id' => $this->poll, 'device_model' => str_repeat('x', 500), 'answers' => [['question_id' => $this->q1, 'answer_id' => $this->a1]]]);
        $this->assertFalse($this->ok($long));
        $this->assertSame(0, DB::table('council_national_poll_votes')->where('member_id', '99900000001')->count());
    }

    public function test_a_deleted_poll_disappears_from_the_app_and_cannot_be_voted_but_its_votes_stay()
    {
        // one vote already cast, then the poll is marked as deleted
        $this->vote($this->user2, ['poll_id' => $this->poll, 'answers' => [['question_id' => $this->q1, 'answer_id' => $this->a1]]]);
        DB::table('council_national_polls')->where('id', $this->poll)->update(['deleted_at' => now()]);

        [, $list] = $this->api('listNationalCouncilPolls', $this->user);
        $this->assertNotContains($this->poll, array_column($list['polls'], 'id'), 'gone from the app list');

        [, $body] = $this->vote($this->user, ['poll_id' => $this->poll, 'answers' => [['question_id' => $this->q1, 'answer_id' => $this->a1]]]);
        $this->assertFalse($this->ok($body), 'and it cannot be voted in');
        $this->assertSame(1, $this->votes($this->poll), 'the earlier vote is still in the database');
    }

    public function test_regular_poll_answers_record_the_phone_too_for_members_and_guests()
    {
        $now = now();
        $pollId = DB::table('polls')->insertGetId(['show' => 1, 'created_at' => $now, 'updated_at' => $now]);
        $optionId = DB::table('polls_options')->insertGetId(['poll_id' => $pollId, 'option' => '{}', 'created_at' => $now, 'updated_at' => $now]);
        $repo = app(ApiRepository::class);

        // a member's answer keeps the installation id, the model and the hardware id
        $repo->userAnswerPoll(AppUser::find($this->user), $optionId, ['device_id' => 'dev-1', 'device_model' => 'Apple iPhone14,5, iOS 17.2', 'hardware_id' => 'IDFV-1']);
        $row = DB::table('users_polls')->where('poll_id', $pollId)->first();
        $this->assertSame('dev-1', $row->device_id);
        $this->assertSame('Apple iPhone14,5, iOS 17.2', $row->device_model);
        $this->assertSame('IDFV-1', $row->hardware_id);

        // changing the answer replaces the row and keeps recording the phone
        $repo->userAnswerPoll(AppUser::find($this->user), $optionId, ['device_id' => 'dev-2', 'device_model' => 'Apple iPhone14,5, iOS 17.2', 'hardware_id' => 'IDFV-1']);
        $this->assertSame(1, DB::table('users_polls')->where('poll_id', $pollId)->count());
        $this->assertSame('dev-2', DB::table('users_polls')->where('poll_id', $pollId)->value('device_id'));

        // an older app sends nothing: still works, the columns stay empty
        $repo->userAnswerPoll(AppUser::find($this->user2), $optionId);
        $this->assertNull(DB::table('users_polls')->where('app_user_id', $this->user2)->value('hardware_id'));

        // a guest's answer keeps them too
        $repo->guestAnswerPoll('guest-dev', $optionId, ['device_id' => 'ignored', 'device_model' => 'Samsung SM-A525F, Android 13', 'hardware_id' => 'ANDROID-ID-9']);
        $guest = DB::table('guest_poll_votes')->where('poll_id', $pollId)->first();
        $this->assertSame('guest-dev', $guest->device_id, 'the guest is still keyed by the device id');
        $this->assertSame('Samsung SM-A525F, Android 13', $guest->device_model);
        $this->assertSame('ANDROID-ID-9', $guest->hardware_id);
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

    // ---- several published polls at once (the app lists them all) ----

    private function api(string $method, int $userId, array $payload = []): array
    {
        $req = Request::create('/api/v2/x', 'POST', $payload);
        $req->merge(['user' => AppUser::find($userId)]);
        $res = app(ApiController::class)->$method($req, app(ApiRepository::class));

        return [$res->getStatusCode(), json_decode($res->getContent(), true)];
    }

    // permit $userId's member in $this->otherPoll as well, so he is in two published polls
    private function permitInOtherPoll(string $memberId, int $weight = 7): void
    {
        DB::table('council_national_poll_permissions')->insert(['poll_id' => $this->otherPoll, 'member_id' => $memberId, 'vote_weight' => $weight, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_the_app_lists_every_published_poll_the_member_is_permitted_for()
    {
        $this->permitInOtherPoll('99900000001');
        $unpublished = DB::table('council_national_polls')->insertGetId(['title' => 'T draft', 'is_published' => 0, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('council_national_poll_permissions')->insert(['poll_id' => $unpublished, 'member_id' => '99900000001', 'vote_weight' => 7, 'created_at' => now(), 'updated_at' => now()]);

        [, $body] = $this->api('listNationalCouncilPolls', $this->user);

        $ids = array_column($body['polls'], 'id');
        $this->assertContains($this->poll, $ids);
        $this->assertContains($this->otherPoll, $ids, 'a second published poll is listed too, not only the latest');
        $this->assertNotContains($unpublished, $ids, 'an unpublished poll never is');
        $this->assertTrue(collect($body['polls'])->every(fn($p) => $p['can_i_vote'] === true && $p['voted'] === false));

        // a member who is only in the first poll sees only that one
        [, $b2] = $this->api('listNationalCouncilPolls', $this->user2);
        $this->assertSame([$this->poll], array_column($b2['polls'], 'id'));
    }

    public function test_voting_one_poll_marks_it_voted_and_leaves_the_other_open()
    {
        $this->permitInOtherPoll('99900000001');

        // vote in the OLDER poll (not the latest one) - must work now
        [, $body] = $this->vote($this->user, ['poll_id' => $this->poll, 'answers' => [['question_id' => $this->q1, 'answer_id' => $this->a1], ['question_id' => $this->q2, 'answer_id' => $this->a2]]]);
        $this->assertTrue($this->ok($body));

        [, $list] = $this->api('listNationalCouncilPolls', $this->user);
        $byId = collect($list['polls'])->keyBy('id');
        $this->assertTrue($byId[$this->poll]['voted']);
        $this->assertFalse($byId[$this->poll]['can_i_vote']);
        $this->assertSame('لقد قمت بالتصويت', $byId[$this->poll]['message']);
        $this->assertTrue($byId[$this->otherPoll]['can_i_vote'], 'the other poll is still open');

        // and the other poll can be voted too, with its own weight
        [, $b2] = $this->vote($this->user, ['poll_id' => $this->otherPoll, 'answers' => [['question_id' => $this->qOther, 'answer_id' => $this->aOther]]]);
        $this->assertTrue($this->ok($b2));
        $this->assertSame(1, $this->votes($this->otherPoll));
    }

    public function test_the_app_gets_one_chosen_poll_with_candidate_photos_but_never_one_it_may_not_vote_in()
    {
        [$pick] = $this->sampleMemberIds(1);
        DB::table('answer_council_national_polls')->where('id', $this->a1)->update(['member_id' => (string) $pick]);
        $this->permitInOtherPoll('99900000001');

        [, $poll] = $this->api('getLatestNationalCouncilPoll', $this->user, ['poll_id' => $this->poll]);
        $this->assertSame($this->poll, $poll['id']);
        $answers = collect($poll['questions'])->pluck('answers')->flatten(1);
        $candidate = $answers->firstWhere('id', $this->a1);
        $this->assertStringContainsString('/api/v2/member-photo/' . $pick, $candidate['photo']);
        $this->assertNull($answers->firstWhere('id', $this->a2)['photo'], 'a hand-typed option has no photo');
        $this->assertArrayNotHasKey('member_id', $candidate, 'the member id itself is not sent to voters');

        // a poll the member is not permitted for is refused
        [, $refused] = $this->api('getLatestNationalCouncilPoll', $this->user2, ['poll_id' => $this->otherPoll]);
        $this->assertArrayNotHasKey('questions', $refused);

        // a poll he has already voted in is refused too
        $this->vote($this->user, ['poll_id' => $this->poll, 'answers' => [['question_id' => $this->q1, 'answer_id' => $this->a1]]]);
        [, $again] = $this->api('getLatestNationalCouncilPoll', $this->user, ['poll_id' => $this->poll]);
        $this->assertArrayNotHasKey('questions', $again);

        // without poll_id the old behaviour (the latest one) still answers, for older app builds.
        // "Latest" = the newest permission row, so make the other poll's clearly newer (both were created in the
        // same second, which made the order a coin toss).
        DB::table('council_national_poll_permissions')->where('poll_id', $this->otherPoll)->where('member_id', '99900000001')->update(['created_at' => now()->addMinute()]);
        [, $old] = $this->api('getLatestNationalCouncilPoll', $this->user);
        $this->assertArrayHasKey('questions', $old);
    }

    public function test_a_poll_candidates_photo_is_served_publicly_but_other_members_stay_private()
    {
        [$pick, $stranger] = $this->sampleMemberIds(2);
        $jpeg = "\xFF\xD8\xFF\xE0" . str_repeat('x', 200);
        DB::table('fpm_users')->where('MemberId', $pick)->update(['PersonImage' => $jpeg]);
        DB::table('fpm_users')->where('MemberId', $stranger)->update(['PersonImage' => $jpeg]);
        DB::table('answer_council_national_polls')->where('id', $this->a1)->update(['member_id' => (string) $pick]);

        $this->get('/api/v2/member-photo/' . $pick)->assertOk();
        $this->get('/api/v2/member-photo/' . $stranger)->assertNotFound();
    }

    // ---- closing time (closes_at) ----

    private function closePoll(int $pollId, $when): void
    {
        DB::table('council_national_polls')->where('id', $pollId)->update(['closes_at' => $when]);
    }

    private function fullVote(int $userId, int $pollId = null): array
    {
        return $this->vote($userId, ['poll_id' => $pollId ?? $this->poll, 'answers' => [['question_id' => $this->q1, 'answer_id' => $this->a1]]]);
    }

    public function test_a_poll_with_a_future_closing_time_is_open_and_tells_the_app_when_it_closes()
    {
        $this->closePoll($this->poll, now()->addHour());

        [, $list] = $this->api('listNationalCouncilPolls', $this->user);
        $row = collect($list['polls'])->firstWhere('id', $this->poll);
        $this->assertTrue($row['can_i_vote']);
        $this->assertFalse($row['closed']);
        $this->assertNotNull($row['closes_at'], 'the app needs it for the countdown');
        $this->assertTrue($this->ok($this->fullVote($this->user)[1]));
    }

    public function test_a_closed_poll_stays_listed_marked_closed_and_cannot_be_opened_or_voted()
    {
        $this->closePoll($this->poll, now()->subMinute());

        [, $list] = $this->api('listNationalCouncilPolls', $this->user);
        $row = collect($list['polls'])->firstWhere('id', $this->poll);
        $this->assertNotNull($row, 'it stays visible until the admin unpublishes it');
        $this->assertTrue($row['closed']);
        $this->assertFalse($row['can_i_vote']);
        $this->assertSame('انتهى وقت التصويت', $row['message']);

        [, $opened] = $this->api('getLatestNationalCouncilPoll', $this->user, ['poll_id' => $this->poll]);
        $this->assertArrayNotHasKey('questions', $opened);

        $this->fullVote($this->user);
        $this->assertSame(0, $this->votes($this->poll), 'no vote is stored after the closing time');
    }

    public function test_the_old_single_poll_check_also_refuses_a_closed_poll()
    {
        $this->closePoll($this->poll, now()->subMinute());
        $req = Request::create('/api/v2/x', 'POST');
        $req->merge(['user' => AppUser::find($this->user)]);
        $this->assertSame(404, app(ApiController::class)->canIVoteToNationalCouncilPoll($req, app(ApiRepository::class)));
    }

    public function test_a_member_who_voted_before_closing_is_shown_as_voted_not_closed()
    {
        $this->assertTrue($this->ok($this->fullVote($this->user)[1]));
        $this->closePoll($this->poll, now()->subMinute());

        [, $list] = $this->api('listNationalCouncilPolls', $this->user);
        $row = collect($list['polls'])->firstWhere('id', $this->poll);
        $this->assertTrue($row['voted']);
        $this->assertFalse($row['can_i_vote']);
        $this->assertSame(1, $this->votes($this->poll), 'closing never removes the votes that were cast');
    }

    public function test_unpublishing_a_closed_poll_makes_it_disappear_and_a_poll_without_a_closing_time_never_closes()
    {
        $this->closePoll($this->poll, now()->subMinute());
        DB::table('council_national_polls')->where('id', $this->poll)->update(['is_published' => 0]);
        [, $list] = $this->api('listNationalCouncilPolls', $this->user);
        $this->assertNotContains($this->poll, array_column($list['polls'] ?? [], 'id'));

        DB::table('council_national_polls')->where('id', $this->poll)->update(['is_published' => 1, 'closes_at' => null]);
        [, $list] = $this->api('listNationalCouncilPolls', $this->user);
        $row = collect($list['polls'])->firstWhere('id', $this->poll);
        $this->assertTrue($row['can_i_vote']);
        $this->assertNull($row['closes_at']);
    }
}
