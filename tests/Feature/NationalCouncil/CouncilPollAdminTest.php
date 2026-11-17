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
}
