<?php

namespace Tests\Feature\NationalCouncil;

use App\Http\Controllers\Admin\NationalCouncilPollController;
use App\V2\CouncilMeeting;
use App\V2\CouncilNationalPoll;
use App\V2\CouncilNationalPollPermission;
use App\V2\CouncilNationalPollVote;
use App\V2\CouncilRosterMember;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CouncilPollAdminTest extends CouncilTestCase
{
    private $ctrl;
    private $members;

    protected function setUp(): void
    {
        parent::setUp();

        auth('admin')->setUser($this->adminUser());
        $this->ctrl = app(NationalCouncilPollController::class);

        // roster of 3, weights 5 / 3 / 2
        $this->members = $this->sampleMemberIds(4);
        CouncilRosterMember::whereNotNull('id')->delete();
        foreach ([5, 3, 2] as $i => $w) {
            CouncilRosterMember::create(['member_id' => $this->members[$i], 'weight' => $w]);
        }
    }

    private function ajax(array $data = []): Request
    {
        return Request::create('/x', 'POST', $data, [], [], ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);
    }

    private function pollFromRoster(): CouncilNationalPoll
    {
        $meeting = new CouncilMeeting(['title' => 'T mtg', 'is_active' => true]);
        $meeting->freezeFromRoster();
        $meeting->save();

        // the controller reads is_published from the global request, so publish explicitly afterwards
        $this->ctrl->store(Request::create('/x', 'POST', ['title' => 'T election', 'from_roster' => 1, 'meeting_id' => $meeting->id]));
        $poll = CouncilNationalPoll::where('title', 'T election')->firstOrFail();
        DB::table('council_national_polls')->where('id', $poll->id)->update(['is_published' => 1]);
        $poll = $poll->fresh();

        return $poll;
    }

    public function test_a_poll_can_load_its_voters_from_the_roster_and_link_a_meeting()
    {
        $poll = $this->pollFromRoster();

        $perms = CouncilNationalPollPermission::where('poll_id', $poll->id)->get();
        $this->assertCount(3, $perms);
        $this->assertEquals(10, $perms->sum('vote_weight'));
        $this->assertNotNull($poll->meeting_id);
    }

    public function test_topping_up_from_the_roster_adds_only_the_missing_member_and_is_repeatable()
    {
        $poll = $this->pollFromRoster();
        CouncilRosterMember::create(['member_id' => $this->members[3], 'weight' => 1]); // the women's rep joins

        $r = $this->ctrl->syncVotersFromRoster($poll->id);
        $this->assertStringContainsString('1 member', $r->getContent());
        $this->assertSame(4, CouncilNationalPollPermission::where('poll_id', $poll->id)->count());

        $this->ctrl->syncVotersFromRoster($poll->id);
        $this->assertSame(4, CouncilNationalPollPermission::where('poll_id', $poll->id)->count(), 'a second run adds nobody');
    }

    public function test_add_one_and_remove_one_voter()
    {
        $poll = $this->pollFromRoster();

        $this->assertSame(200, $this->ctrl->addVoter($this->ajax(['member_id' => $this->members[3], 'weight' => 1]), $poll->id)->getStatusCode());
        $this->assertSame(422, $this->ctrl->addVoter($this->ajax(['member_id' => $this->members[3], 'weight' => 1]), $poll->id)->getStatusCode(), 'twice is refused');

        $perm = CouncilNationalPollPermission::where('poll_id', $poll->id)->where('member_id', $this->members[3])->first();
        $this->assertSame(200, $this->ctrl->removeVoter($poll->id, $perm->id)->getStatusCode());
        $this->assertSame(3, CouncilNationalPollPermission::where('poll_id', $poll->id)->count());
    }

    private function castVotes(CouncilNationalPoll $poll): array
    {
        $now = now();
        $q = DB::table('council_national_poll_questions')->insertGetId(['poll_id' => $poll->id, 'question' => 'Pick', 'created_at' => $now, 'updated_at' => $now]);
        $ans = [];
        foreach (['A', 'B', 'C'] as $name) {
            $ans[$name] = DB::table('answer_council_national_polls')->insertGetId(['question_id' => $q, 'answer' => $name, 'created_at' => $now, 'updated_at' => $now]);
        }

        // member0 (w5) -> A ; member1 (w3) -> B ; member2 (w2) -> B   => A 5, B 5, C 0
        foreach ([[0, 'A'], [1, 'B'], [2, 'B']] as [$i, $a]) {
            $uid = $this->makeAppUser('T' . $i, $this->members[$i]);
            $w = CouncilNationalPollPermission::where('poll_id', $poll->id)->where('member_id', $this->members[$i])->value('vote_weight');
            CouncilNationalPollVote::create(['user_id' => $uid, 'member_id' => $this->members[$i], 'poll_id' => $poll->id, 'question_id' => $q, 'answer_id' => $ans[$a], 'weight' => $w, 'voted_at' => $now]);
        }

        return [$q, $ans];
    }

    public function test_a_member_who_already_voted_cannot_be_removed()
    {
        $poll = $this->pollFromRoster();
        $this->castVotes($poll);

        $permId = CouncilNationalPollPermission::where('poll_id', $poll->id)->where('member_id', $this->members[0])->value('id');

        $this->assertSame(422, $this->ctrl->removeVoter($poll->id, $permId)->getStatusCode());
    }

    public function test_results_are_ranked_by_weight_with_ties_and_cutoff_flagged()
    {
        $poll = $this->pollFromRoster();
        [$q] = $this->castVotes($poll);

        $view = $this->ctrl->results(Request::create('/x', 'GET', ['top' => 1]), $poll->id);
        $data = $view->getData();
        $rows = $data['ranking'][$q]['rows'];

        $this->assertSame([0, 5, 5], $rows->pluck('weight')->sort()->values()->all());
        $this->assertSame(1, $rows->where('answer', 'A')->first()['rank']);
        $this->assertSame(1, $rows->where('answer', 'B')->first()['rank']);
        $this->assertSame(3, $rows->where('answer', 'C')->first()['rank']);
        $this->assertTrue($data['ranking'][$q]['tie_at_cutoff'], 'A and B tie across the top-1 line');
        $this->assertSame(3, $data['electorsCount']);
        $this->assertSame(3, $data['votersCount']);
        $this->assertSame(10, $data['totalWeight']);
        $this->assertNotNull($data['meetingStats']);
        $this->assertStringContainsString('عدد الأصوات', $view->render());
    }

    public function test_delete_all_data_is_refused_on_a_published_poll_and_a_poll_with_votes_cannot_be_deleted()
    {
        $poll = $this->pollFromRoster();
        $this->castVotes($poll);

        $this->ctrl->clear($poll->id);
        $this->assertSame(3, DB::table('council_national_poll_votes')->where('poll_id', $poll->id)->count(), 'published: not cleared');

        DB::table('council_national_polls')->where('id', $poll->id)->update(['is_published' => 0]);
        $this->ctrl->destroy($poll->id);
        $this->assertTrue(DB::table('council_national_polls')->where('id', $poll->id)->exists(), 'has votes: not deleted');
    }

    public function test_the_poll_pages_render_with_the_voter_tools()
    {
        $poll = $this->pollFromRoster();
        $admin = $this->adminUser();

        $edit = $this->actingAs($admin, 'admin')->get(route('admin.national-council-poll.edit', $poll->id, false));
        $edit->assertOk();
        $this->assertStringContainsString('syncRosterBtn', $edit->getContent());
        $this->assertStringContainsString('pv-remove', $edit->getContent());

        $create = $this->actingAs($admin, 'admin')->get(route('admin.national-council-poll.create', [], false));
        $create->assertOk();
        $this->assertStringContainsString('from_roster', $create->getContent());
    }

    // what the Publish tickbox in the rendered form would send when ticked
    private function publishTickValue(string $html): string
    {
        preg_match('#<input\s+type="checkbox" name="is_published"[^>]*value="([^"]*)"#', $html, $m);

        return $m[1] ?? 'MISSING';
    }

    public function test_ticking_publish_on_the_create_and_edit_forms_really_publishes()
    {
        $poll = $this->pollFromRoster();
        DB::table('council_national_polls')->where('id', $poll->id)->update(['is_published' => 0]);
        $admin = $this->adminUser();
        $this->actingAs($admin, 'admin');

        // the tickbox must send a truthy value, not the poll's old state ("0") or an empty string
        $create = $this->get(route('admin.national-council-poll.create', [], false))->assertOk()->getContent();
        $this->assertSame('1', $this->publishTickValue($create), 'create form');
        $edit = $this->get(route('admin.national-council-poll.edit', $poll->id, false))->assertOk()->getContent();
        $this->assertSame('1', $this->publishTickValue($edit), 'edit form of an UNPUBLISHED poll');

        // saving the edit form with the box ticked publishes the poll; leaving it unticked unpublishes it
        $this->put(route('admin.national-council-poll.update', $poll->id, false), ['title' => 'T election', 'is_published' => $this->publishTickValue($edit)])->assertRedirect();
        $this->assertSame(1, (int) DB::table('council_national_polls')->where('id', $poll->id)->value('is_published'));
        $this->put(route('admin.national-council-poll.update', $poll->id, false), ['title' => 'T election'])->assertRedirect();
        $this->assertSame(0, (int) DB::table('council_national_polls')->where('id', $poll->id)->value('is_published'));

        // creating a poll with the box ticked publishes it
        $this->post(route('admin.national-council-poll.store', [], false), ['title' => 'T created published', 'is_published' => $this->publishTickValue($create)])->assertRedirect();
        $this->assertSame(1, (int) DB::table('council_national_polls')->where('title', 'T created published')->value('is_published'));
    }

    public function test_the_polls_list_has_a_publish_and_unpublish_button()
    {
        $poll = $this->pollFromRoster();
        DB::table('council_national_polls')->where('id', $poll->id)->update(['is_published' => 0]);
        $this->actingAs($this->adminUser(), 'admin');
        $toggle = route('admin.national-council-poll.toggle-publish', $poll->id, false);

        // an unpublished poll shows "Publish" in the list
        $row = collect($this->getJson(route('admin.national-council-poll.index', [], false), ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()->json('data'))->firstWhere('id', $poll->id);
        $this->assertStringContainsString('>Publish</a>', $row['is_published']);

        // a poll with no question cannot be published
        $this->get($toggle)->assertRedirect();
        $this->assertSame(0, (int) DB::table('council_national_polls')->where('id', $poll->id)->value('is_published'));

        // with a question it can, and the button flips to Unpublish; one more click unpublishes it again
        DB::table('council_national_poll_questions')->insert(['poll_id' => $poll->id, 'question' => 'T q', 'created_at' => now(), 'updated_at' => now()]);
        $this->get($toggle)->assertRedirect();
        $this->assertSame(1, (int) DB::table('council_national_polls')->where('id', $poll->id)->value('is_published'));
        $row = collect($this->getJson(route('admin.national-council-poll.index', [], false), ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()->json('data'))->firstWhere('id', $poll->id);
        $this->assertStringContainsString('>Unpublish</a>', $row['is_published']);
        $this->get($toggle)->assertRedirect();
        $this->assertSame(0, (int) DB::table('council_national_polls')->where('id', $poll->id)->value('is_published'));
    }

    public function test_a_question_can_take_candidates_picked_from_the_member_list()
    {
        $poll = $this->pollFromRoster();
        $admin = $this->adminUser();
        $this->actingAs($admin, 'admin');
        [, , , $pick] = $this->members;                       // a member who is NOT on the roster
        $pickName = DB::table('fpm_users')->where('MemberId', $pick)->value('UserFullName');

        // the page shows the picker and the corrected button label
        $create = $this->get(route('admin.national-council-poll.questions.create', $poll->id, false))->assertOk()->getContent();
        $this->assertStringContainsString('candSearchInput', $create);

        // jQuery is loaded at the bottom of the layout, so the picker's script must come AFTER it and must not
        // be an inline <script> in the form (it would run before $ exists and the Search button would do nothing)
        $jq = strpos($create, 'jquery.min.js');
        $js = strpos($create, 'council-poll-candidates.js');
        $this->assertNotFalse($js, 'the picker script is loaded on the Add Question page');
        $this->assertGreaterThan($jq, $js, 'and it is loaded after jQuery');
        $picker = substr($create, strpos($create, 'id="candPicker"'), 1500);
        $this->assertStringNotContainsString('<script>', substr($picker, 0, strpos($picker, 'Picking a member') ?: 1500), 'no inline script inside the picker markup');
        $this->assertStringContainsString('data-search-url="', $create);
        $this->get(route('admin.national-council-poll.questions.create', $poll->id, false));   // (same page again, no state)

        $index = $this->get(route('admin.national-council-poll.questions.index', $poll->id, false))->assertOk()->getContent();
        $this->assertStringContainsString('>Add Question<', $index);
        $this->assertStringNotContainsString('Add National Council Poll', $index);

        // the search finds any member of the member list (by id), with a photo url for the preview
        $found = $this->getJson(route('admin.national-council-poll.candidates.search', $poll->id, false) . '?q=' . $pick)->assertOk()->json();
        $this->assertContains((string) $pick, array_column($found, 'member_id'));
        $this->assertStringContainsString('/candidates/photo/' . $pick, $found[0]['photo']);
        $this->getJson(route('admin.national-council-poll.candidates.search', $poll->id, false) . '?q=a')->assertOk()->assertExactJson([]);   // too short

        // saving: the picked member keeps his member id, a hand-typed option keeps none
        $this->post(route('admin.national-council-poll.questions.store', $poll->id, false), [
            'question' => 'من تختارون؟',
            'options' => [
                ['name_ar' => $pickName, 'member_id' => (string) $pick],
                ['name_ar' => 'خيار مكتوب بخط اليد'],
            ],
        ])->assertRedirect();
        $answers = DB::table('answer_council_national_polls')->orderBy('id')->get();
        $this->assertSame((string) $pick, $answers[0]->member_id);
        $this->assertNull($answers[1]->member_id);

        // editing the question keeps who the candidate is
        $question = DB::table('council_national_poll_questions')->where('poll_id', $poll->id)->first();
        $this->put(route('admin.national-council-poll.questions.update', [$poll->id, $question->id], false), [
            'question' => 'من تختارون؟ (معدل)',
            'options' => [
                ['id' => $answers[0]->id, 'name_ar' => $pickName . ' (معدل)'],
                ['id' => $answers[1]->id, 'name_ar' => 'خيار مكتوب بخط اليد'],
            ],
        ])->assertRedirect();
        $this->assertSame((string) $pick, DB::table('answer_council_national_polls')->where('id', $answers[0]->id)->value('member_id'));
    }
}
