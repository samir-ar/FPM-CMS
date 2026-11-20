<?php

namespace Tests\Feature\NationalCouncil;

use App\Http\Repositories\V2\FpmApisRepository;
use App\V2\CouncilNationalPoll;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

// "Reset votes" on a council poll: locked with an SMS code sent to the one fixed system phone; clears the votes of THAT
// poll only, after copying every one into council_poll_vote_resets.
class PollResetVotesTest extends CouncilTestCase
{
    private array $sms = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.internal_election_results_otp_phone' => '96100000000']);
        $this->sms = [];
        $sms = &$this->sms;
        $this->mock(FpmApisRepository::class, function ($m) use (&$sms) {
            $m->shouldReceive('sendSMS')->andReturnUsing(function ($data) use (&$sms) { $sms[] = $data; return null; });
        });
        Cache::flush();
    }

    private static int $seq = 0;

    // a poll with one question, one candidate and two votes (two different members)
    private function makeVotablePoll(): object
    {
        $now = now();
        $n = ++self::$seq;
        $id = DB::table('council_national_polls')->insertGetId(['title' => 'T reset poll ' . $n, 'is_published' => 1, 'created_at' => $now, 'updated_at' => $now]);
        $q = DB::table('council_national_poll_questions')->insertGetId(['poll_id' => $id, 'question' => 'Q', 'created_at' => $now, 'updated_at' => $now]);
        $a = DB::table('answer_council_national_polls')->insertGetId(['question_id' => $q, 'answer' => 'A', 'created_at' => $now, 'updated_at' => $now]);
        foreach ([1, 2] as $i) {
            $member = '9990' . $n . '000' . $i;
            $user = $this->makeAppUser('T reset voter ' . $n . '-' . $i, $member);
            DB::table('council_national_poll_votes')->insert([
                'user_id' => $user, 'member_id' => $member, 'poll_id' => $id, 'question_id' => $q, 'answer_id' => $a,
                'weight' => 1, 'device_id' => 'test', 'voted_at' => $now, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        return (object) ['id' => $id];
    }

    private function pollWithVotes(): array
    {
        return [$this->makeVotablePoll(), $this->makeVotablePoll()];
    }

    private function codeFromSms(): string
    {
        $this->assertNotEmpty($this->sms, 'a code SMS was sent');
        preg_match('/(\d{6})/', end($this->sms)['message'], $m);
        return $m[1];
    }

    public function test_the_page_asks_for_a_code_and_nothing_is_cleared_without_it(): void
    {
        [$poll, $other] = $this->pollWithVotes();
        $before = DB::table('council_national_poll_votes')->where('poll_id', $poll->id)->count();
        $admin = $this->adminUser();

        $this->actingAs($admin, 'admin')->get(route('admin.national-council-poll.reset-votes', $poll->id, false))->assertOk()->assertSee('Send code by SMS');

        // no code at all / a wrong code: refused, votes still there
        $this->actingAs($admin, 'admin')->post(route('admin.national-council-poll.reset-votes.confirm', $poll->id, false), ['code' => '123456'])->assertSessionHasErrors();
        $this->assertSame($before, DB::table('council_national_poll_votes')->where('poll_id', $poll->id)->count());
    }

    public function test_sending_the_code_texts_the_fixed_phone(): void
    {
        [$poll] = $this->pollWithVotes();
        $this->actingAs($this->adminUser(), 'admin')->post(route('admin.national-council-poll.reset-votes.send', $poll->id, false))->assertSessionHas('message');

        $this->assertCount(1, $this->sms);
        $this->assertSame('96100000000', $this->sms[0]['phone_number']);
        $this->assertMatchesRegularExpression('/\d{6}/', $this->sms[0]['message']);
    }

    public function test_the_right_code_clears_only_that_poll_and_keeps_a_copy(): void
    {
        [$poll, $other] = $this->pollWithVotes();
        $mine = DB::table('council_national_poll_votes')->where('poll_id', $poll->id)->count();
        $theirs = DB::table('council_national_poll_votes')->where('poll_id', $other->id)->count();
        $this->assertGreaterThan(0, $mine);
        $admin = $this->adminUser();

        $this->actingAs($admin, 'admin')->post(route('admin.national-council-poll.reset-votes.send', $poll->id, false));
        $code = $this->codeFromSms();

        $this->actingAs($admin, 'admin')->post(route('admin.national-council-poll.reset-votes.confirm', $poll->id, false), ['code' => $code])->assertRedirect(route('admin.national-council-poll.index', [], false));

        $this->assertSame(0, DB::table('council_national_poll_votes')->where('poll_id', $poll->id)->count(), 'the poll was cleared');
        $this->assertSame($theirs, DB::table('council_national_poll_votes')->where('poll_id', $other->id)->count(), 'another poll is untouched');
        $this->assertSame($mine, DB::table('council_poll_vote_resets')->where('poll_id', $poll->id)->count(), 'every vote was copied first');

        // the code works once
        $this->actingAs($admin, 'admin')->post(route('admin.national-council-poll.reset-votes.confirm', $other->id, false), ['code' => $code])->assertSessionHasErrors();
        $this->assertSame($theirs, DB::table('council_national_poll_votes')->where('poll_id', $other->id)->count());
    }

    public function test_a_code_for_one_poll_does_not_open_another_poll(): void
    {
        [$poll, $other] = $this->pollWithVotes();
        $admin = $this->adminUser();
        $this->actingAs($admin, 'admin')->post(route('admin.national-council-poll.reset-votes.send', $poll->id, false));
        $code = $this->codeFromSms();
        $keep = DB::table('council_national_poll_votes')->where('poll_id', $other->id)->count();

        $this->actingAs($admin, 'admin')->post(route('admin.national-council-poll.reset-votes.confirm', $other->id, false), ['code' => $code])->assertSessionHasErrors();
        $this->assertSame($keep, DB::table('council_national_poll_votes')->where('poll_id', $other->id)->count());
    }

    public function test_five_wrong_codes_burn_the_code(): void
    {
        [$poll] = $this->pollWithVotes();
        $admin = $this->adminUser();
        $this->actingAs($admin, 'admin')->post(route('admin.national-council-poll.reset-votes.send', $poll->id, false));
        $code = $this->codeFromSms();
        $wrong = $code === '111111' ? '222222' : '111111';

        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($admin, 'admin')->post(route('admin.national-council-poll.reset-votes.confirm', $poll->id, false), ['code' => $wrong])->assertSessionHasErrors();
        }
        // even the right code no longer works: a new one has to be asked for
        $this->actingAs($admin, 'admin')->post(route('admin.national-council-poll.reset-votes.confirm', $poll->id, false), ['code' => $code])->assertSessionHasErrors();
        $this->assertGreaterThan(0, DB::table('council_national_poll_votes')->where('poll_id', $poll->id)->count());
    }

    public function test_the_poll_list_has_the_reset_link(): void
    {
        [$poll] = $this->pollWithVotes();
        $res = $this->actingAs($this->adminUser(), 'admin')->get(route('admin.national-council-poll.index', [], false), ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();
        $this->assertStringContainsString('reset-votes', $res->getContent());
    }
}
