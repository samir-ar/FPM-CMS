<?php

namespace Tests\Feature\NationalCouncil;

use Illuminate\Support\Facades\DB;

// The voting TV screen: who has voted, live; never what anybody voted, no member ids on the data it shows as text,
// no phones or devices.
class CouncilVotingScreenTest extends CouncilTestCase
{
    private int $poll;
    private array $m;

    protected function setUp(): void
    {
        parent::setUp();
        $now = now();
        $this->m = $this->sampleMemberIds(4);   // m0, m1, m2 are voters (m0 and m1 voted); m3 is not on the list
        $this->poll = DB::table('council_national_polls')->insertGetId(['title' => 'T screen', 'is_published' => 1, 'closes_at' => $now->copy()->addMinutes(10), 'created_at' => $now, 'updated_at' => $now]);
        $question = DB::table('council_national_poll_questions')->insertGetId(['poll_id' => $this->poll, 'question' => 'من تختارون؟', 'created_at' => $now, 'updated_at' => $now]);
        $answer = DB::table('answer_council_national_polls')->insertGetId(['question_id' => $question, 'answer' => 'مرشحة سرية', 'created_at' => $now, 'updated_at' => $now]);
        foreach ([0, 1, 2] as $i) {
            DB::table('council_national_poll_permissions')->insert(['poll_id' => $this->poll, 'member_id' => (string) $this->m[$i], 'vote_weight' => $i + 1, 'created_at' => $now, 'updated_at' => $now]);
        }
        foreach ([0, 1] as $i) {
            DB::table('council_national_poll_votes')->insert([
                'user_id' => $this->makeAppUser('T screen voter ' . $i, (string) $this->m[$i], ['phone_number' => '96170999' . $i . '11']), 'member_id' => (string) $this->m[$i],
                'poll_id' => $this->poll, 'question_id' => $question, 'answer_id' => $answer, 'weight' => $i + 1, 'device_id' => 'SCREEN-DEVICE-' . $i,
                'device_model' => 'Samsung SM-SECRET', 'voted_at' => $now->copy()->subMinutes(5 - $i), 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        $this->actingAs($this->adminUser(), 'admin');
    }

    public function test_the_screen_data_counts_the_voters_and_hides_everything_private(): void
    {
        $response = $this->getJson(route('admin.national-council-poll.board-data', $this->poll, false))->assertOk();
        $d = $response->json();

        $this->assertSame(['member_count' => 3, 'present_count' => 2, 'absent_count' => 1, 'total_weight' => 6, 'present_weight' => 3], $d['stats']);
        $this->assertSame('LIVE', $d['meeting']['state']);
        $this->assertGreaterThan(500, $d['meeting']['seconds_left']);
        $this->assertSame([(string) $this->m[1], (string) $this->m[0]], array_column($d['recent'], 'id'), 'newest voter first');
        $this->assertCount(3, $d['members']);
        $text = json_encode($d, JSON_UNESCAPED_UNICODE);
        foreach (['مرشحة سرية', 'SCREEN-DEVICE', 'SM-SECRET', '96170999'] as $secret) {
            $this->assertStringNotContainsString($secret, $text);
        }
    }

    public function test_the_screen_page_opens_and_is_linked_from_the_voters_page(): void
    {
        $html = $this->get(route('admin.national-council-poll.board', $this->poll, false))->assertOk()->getContent();
        $this->assertStringContainsString('Live voting', $html);
        $this->assertStringContainsString('Latest voters', $html);
        $this->assertStringContainsString('screen-data', $html);
        $this->assertStringNotContainsString('مرشحة سرية', $html);

        $voters = $this->get(route('admin.national-council-poll.progress', $this->poll, false))->assertOk()->getContent();
        $this->assertStringContainsString(route('admin.national-council-poll.board', $this->poll), $voters);
    }

    public function test_closed_and_unpublished_polls_say_so(): void
    {
        $url = route('admin.national-council-poll.board-data', $this->poll, false);
        DB::table('council_national_polls')->where('id', $this->poll)->update(['closes_at' => now()->subMinute()]);
        $this->assertSame('CLOSED', $this->getJson($url)->json('meeting.state'));
        $this->assertSame(0, $this->getJson($url)->json('meeting.seconds_left'));

        DB::table('council_national_polls')->where('id', $this->poll)->update(['closes_at' => null, 'is_published' => 0]);
        $this->assertSame('NOT OPEN', $this->getJson($url)->json('meeting.state'));
    }

    public function test_photos_are_only_served_for_people_on_the_voter_list(): void
    {
        $this->get(route('admin.national-council-poll.board-photo', [$this->poll, $this->m[3]], false))->assertNotFound();
    }
}
