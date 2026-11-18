<?php

namespace Tests\Feature\NationalCouncil;

use Illuminate\Support\Facades\DB;

// The two National Council poll reports: the VOTERS page (who voted, which device, never what) and the hidden
// WHO-VOTED-FOR-WHOM report behind the one-time code.
class CouncilPollVotersTest extends CouncilTestCase
{
    private int $poll;
    private int $question;
    private int $answerA;
    private int $answerB;
    private array $m;

    protected function setUp(): void
    {
        parent::setUp();
        auth('admin')->setUser($this->adminUser());

        $now = now();
        $this->m = $this->sampleMemberIds(4);   // m0, m1 vote from the SAME phone; m2 votes from another; m3 never votes

        $this->poll = DB::table('council_national_polls')->insertGetId(['title' => 'T who voted', 'is_published' => 1, 'created_at' => $now, 'updated_at' => $now]);
        $this->question = DB::table('council_national_poll_questions')->insertGetId(['poll_id' => $this->poll, 'question' => 'من تختارون؟', 'created_at' => $now, 'updated_at' => $now]);
        $this->answerA = DB::table('answer_council_national_polls')->insertGetId(['question_id' => $this->question, 'answer' => 'مرشحة الف', 'created_at' => $now, 'updated_at' => $now]);
        $this->answerB = DB::table('answer_council_national_polls')->insertGetId(['question_id' => $this->question, 'answer' => 'مرشحة باء', 'created_at' => $now, 'updated_at' => $now]);

        foreach ($this->m as $i => $member) {
            DB::table('council_national_poll_permissions')->insert(['poll_id' => $this->poll, 'member_id' => (string) $member, 'vote_weight' => $i + 1, 'created_at' => $now, 'updated_at' => $now]);
        }

        $vote = function (int $i, int $answer, string $device, ?string $model) use ($now) {
            $userId = $this->makeAppUser('T voter ' . $i, (string) $this->m[$i]);
            DB::table('council_national_poll_votes')->insert([
                'user_id' => $userId, 'member_id' => (string) $this->m[$i], 'poll_id' => $this->poll, 'question_id' => $this->question,
                'answer_id' => $answer, 'weight' => $i + 1, 'device_id' => $device, 'device_model' => $model, 'voted_at' => $now->copy()->addSeconds($i), 'created_at' => $now, 'updated_at' => $now,
            ]);
        };
        $vote(0, $this->answerA, 'DEVICE-SHARED-0000-1111', 'Samsung SM-A525F, Android 13');
        $vote(1, $this->answerB, 'DEVICE-SHARED-0000-1111', 'Samsung SM-A525F, Android 13');
        $vote(2, $this->answerA, 'DEVICE-OTHER-2222-3333', null);   // an older app build: no model sent
    }

    public function test_the_voters_page_shows_who_voted_and_the_device_but_never_what_anybody_voted()
    {
        $html = $this->actingAs($this->adminUser(), 'admin')->get(route('admin.national-council-poll.progress', $this->poll, false))->assertOk()->getContent();

        // 4 allowed, 3 voted, 1 to go
        $this->assertMatchesRegularExpression('#Voters allowed</div><div class="n">4</div>#', $html);
        $this->assertMatchesRegularExpression('#Voted</div><div class="n text-success">3</div>#', $html);
        $this->assertMatchesRegularExpression('#Still to vote</div><div class="n text-danger">1</div>#', $html);

        // the phone used for two members is flagged, the other is not
        $this->assertStringContainsString('shared by 2', $html);
        $this->assertStringContainsString('DEVICE-SHARED', $html);
        $this->assertStringContainsString('DEVICE-OTHER-', $html);
        $this->assertStringContainsString('A device was used to vote for more than one member', $html);

        // the phone model is shown next to the device (and once in the shared-device warning)
        $this->assertGreaterThanOrEqual(3, substr_count($html, 'Samsung SM-A525F, Android 13'));

        // secrecy: the candidates' names never appear on this page
        $this->assertStringNotContainsString('مرشحة الف', $html);
        $this->assertStringNotContainsString('مرشحة باء', $html);
    }

    public function test_the_voters_page_filters_voted_and_not_voted_and_exports()
    {
        $this->actingAs($this->adminUser(), 'admin');

        $notVoted = $this->get(route('admin.national-council-poll.progress', $this->poll, false) . '?status=not_voted')->assertOk()->getContent();
        $this->assertStringContainsString('#10006; not yet', str_replace('&#10006;', '#10006;', $notVoted));
        $this->assertSame(1, substr_count($notVoted, 'class="not-voted"'));

        $shared = $this->get(route('admin.national-council-poll.progress', $this->poll, false) . '?shared=1&status=voted')->assertOk()->getContent();
        $this->assertSame(2, substr_count($shared, 'shared by 2</span>'), 'only the two members on the shared phone');

        $this->get(route('admin.national-council-poll.progress.export', $this->poll, false))->assertOk()
            ->assertHeader('content-disposition');
    }

    public function test_who_voted_for_whom_is_locked_behind_the_one_time_code()
    {
        $this->actingAs($this->adminUser(), 'admin');
        $url = route('admin.national-council-poll.votes-report', $this->poll, false);

        // no code yet: sent to the code page, nothing is shown
        $res = $this->get($url);
        $res->assertRedirect();
        $this->assertStringContainsString('results-otp', $res->headers->get('Location'));
        $this->get(route('admin.national-council-poll.votes-report.export', $this->poll, false))->assertRedirect();

        // with the code verified: the report opens
        $html = $this->withSession(['ie_results_otp_verified_until' => now()->addMinutes(5)])->get($url)->assertOk()->getContent();
        $this->assertStringContainsString('مرشحة الف', $html);
        $this->assertStringContainsString('مرشحة باء', $html);
        $this->assertStringContainsString('shared by 2', $html);
        $this->assertSame(2, substr_count($html, '<strong>Samsung SM-A525F, Android 13</strong>'), 'the model of the two votes that carry one');
        $this->assertSame(3, substr_count($html, 'class="dev"'), 'one row per vote');
    }

    public function test_the_report_filters_by_candidate_and_exports_when_unlocked()
    {
        $this->actingAs($this->adminUser(), 'admin')->withSession(['ie_results_otp_verified_until' => now()->addMinutes(5)]);
        $url = route('admin.national-council-poll.votes-report', $this->poll, false);

        $forA = $this->get($url . '?answer_id=' . $this->answerA)->assertOk()->getContent();
        $this->assertStringContainsString('2 of 3 votes', $forA);
        $this->assertStringNotContainsString('<strong>مرشحة باء</strong>', $forA);

        $this->get(route('admin.national-council-poll.votes-report.export', $this->poll, false) . '?answer_id=' . $this->answerB)->assertOk()
            ->assertHeader('content-disposition');
    }

    public function test_the_internal_election_dispute_page_shows_the_phone_model_too()
    {
        $vote = new \App\V2\InternalElectionVote();
        $vote->device_id = 'IE-DEVICE-AAAA-BBBB';
        $vote->device_model = 'Apple iPhone14,5, iOS 17.2';
        $vote->voted_at = now();
        $vote->setRelation('user', (object) ['name' => 'T ie voter', 'member_id' => (string) $this->m[0]]);

        $html = view('internal-election.votes')->with([
            'election' => (object) ['id' => 1, 'title' => 'T election'],
            'allStates' => collect([(object) ['id' => 7, 'name' => 'T district']]),
            'selectedStateId' => 7, 'towns' => collect(), 'selectedTown' => null, 'onlyDuplicateDevices' => false,
            'suspiciousDeviceIds' => collect(), 'votes' => collect([$vote]),
            'errors' => new \Illuminate\Support\ViewErrorBag(),   // normally shared by the web middleware
        ])->render();

        $this->assertStringContainsString('<strong>Apple iPhone14,5, iOS 17.2</strong>', $html);
        $this->assertStringContainsString('IE-DEVICE-AAAA-BBBB', $html);
    }

    public function test_the_who_voted_for_whom_page_is_not_linked_from_the_polls_list()
    {
        $this->actingAs($this->adminUser(), 'admin');
        $list = json_encode($this->getJson(route('admin.national-council-poll.index', [], false), ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()->json('data'));

        $this->assertStringContainsString('/voters', $list, 'the voters page IS linked');
        $this->assertStringNotContainsString('votes-report', $list, 'the who-voted-for-whom page is not');

        // the red "delete all data" button is gone from the list, for published and unpublished polls alike
        $this->assertStringNotContainsString('DELETE ALL DATA', $list);
        $this->assertStringNotContainsString('/clear', $list);
        DB::table('council_national_polls')->where('id', $this->poll)->update(['is_published' => 0]);
        $unpublished = json_encode($this->getJson(route('admin.national-council-poll.index', [], false), ['X-Requested-With' => 'XMLHttpRequest'])->json('data'));
        $this->assertStringNotContainsString('DELETE ALL DATA', $unpublished);
        $this->assertStringNotContainsString('votes-report', $this->get(route('admin.national-council-poll.progress', $this->poll, false))->getContent());
    }
}
