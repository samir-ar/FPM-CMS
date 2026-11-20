<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\Feature\NationalCouncil\CouncilTestCase;

// The results page of one regular poll: same numbers as the "User Polls" list, static charts, members vs guests.
class PollResultsPageTest extends CouncilTestCase
{
    private function poll(): array
    {
        $now = now();
        $id = DB::table('polls')->insertGetId(['question' => json_encode(['ar' => 'هل يعجبك التطبيق؟', 'en' => 'Do you like the app?'], JSON_UNESCAPED_UNICODE), 'show' => 1, 'show_for_guest' => 1, 'expiry_date' => $now->copy()->addDay(), 'created_at' => $now, 'updated_at' => $now]);
        $yes = DB::table('polls_options')->insertGetId(['poll_id' => $id, 'option' => json_encode(['ar' => 'نعم', 'en' => 'Yes'], JSON_UNESCAPED_UNICODE), 'created_at' => $now, 'updated_at' => $now]);
        $no = DB::table('polls_options')->insertGetId(['poll_id' => $id, 'option' => json_encode(['ar' => 'لا', 'en' => 'No'], JSON_UNESCAPED_UNICODE), 'created_at' => $now, 'updated_at' => $now]);

        return [$id, $yes, $no];
    }

    public function test_the_page_shows_totals_members_guests_and_a_chart_per_option(): void
    {
        [$id, $yes, $no] = $this->poll();
        $now = now();
        foreach ([['5640000001', $yes], ['5640000002', $yes], ['5640000003', $no]] as [$member, $opt]) {
            $u = $this->makeAppUser('T ' . $member, $member);
            DB::table('users_polls')->insert(['app_user_id' => $u, 'poll_id' => $id, 'option_id' => $opt, 'member_key' => $member, 'created_at' => $now, 'updated_at' => $now]);
        }
        DB::table('guest_poll_votes')->insert(['device_id' => 'T-g1', 'poll_id' => $id, 'option_id' => $yes, 'hardware_id' => 'HW-G1', 'created_at' => $now, 'updated_at' => $now]);

        $html = $this->actingAs($this->adminUser(), 'admin')->get(route('admin.polls.results', $id, false))->assertOk()->getContent();

        $this->assertStringContainsString('هل يعجبك التطبيق؟', $html);
        $this->assertStringContainsString('Total voters', $html);
        $this->assertStringContainsString('<div class="v">4</div>', $html, 'total = 3 members + 1 guest');
        $this->assertStringContainsString('Members 3', $html);
        $this->assertStringContainsString('Guests 1', $html);
        $this->assertStringContainsString('75%', $html, 'yes = 3 of 4');
        $this->assertStringContainsString('25%', $html);
        $this->assertStringContainsString('<svg class="donut"', $html);
        $this->assertStringContainsString('class="timeline"', $html);
        $this->assertStringContainsString('Leading option', $html);
    }

    public function test_a_poll_without_answers_still_renders(): void
    {
        [$id] = $this->poll();

        $html = $this->actingAs($this->adminUser(), 'admin')->get(route('admin.polls.results', $id, false))->assertOk()->getContent();

        $this->assertStringContainsString('No answers yet', $html);
    }

    public function test_a_tie_is_called_a_tie(): void
    {
        [$id, $yes, $no] = $this->poll();
        $now = now();
        foreach ([['5650000001', $yes], ['5650000002', $no]] as [$member, $opt]) {
            $u = $this->makeAppUser('T ' . $member, $member);
            DB::table('users_polls')->insert(['app_user_id' => $u, 'poll_id' => $id, 'option_id' => $opt, 'member_key' => $member, 'created_at' => $now, 'updated_at' => $now]);
        }

        $html = $this->actingAs($this->adminUser(), 'admin')->get(route('admin.polls.results', $id, false))->assertOk()->getContent();

        $this->assertStringContainsString('Tied in the lead', $html);
    }

    public function test_the_answers_list_and_the_polls_list_link_to_the_results_page(): void
    {
        [$id] = $this->poll();
        $admin = $this->adminUser();

        $list = $this->actingAs($admin, 'admin')->get(route('admin.userPolls.index', ['poll_id' => $id], false))->assertOk()->getContent();
        $this->assertStringContainsString(route('admin.polls.results', $id, false), $list);
    }

    public function test_a_missing_poll_is_a_404(): void
    {
        $this->actingAs($this->adminUser(), 'admin')->get(route('admin.polls.results', 99999999, false))->assertNotFound();
    }
}
