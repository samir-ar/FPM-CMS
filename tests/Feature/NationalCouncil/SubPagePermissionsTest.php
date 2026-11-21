<?php

namespace Tests\Feature\NationalCouncil;

use Illuminate\Support\Facades\DB;

// Every page of a group has its OWN permission line: an admin can be allowed just ONE page of National Council (or Polls,
// or Internal Election) without getting its siblings.
class SubPagePermissionsTest extends CouncilTestCase
{
    private function adminWithOnly(string $pageUrl, string $level): \App\User
    {
        $profileId = DB::table('profiles')->insertGetId(['name' => 'T limited ' . uniqid(), 'created_at' => now(), 'updated_at' => now()]);
        $pageId = DB::table('pages')->where('url', $pageUrl)->value('id');
        $this->assertNotNull($pageId, "page row for $pageUrl");
        DB::table('profile_permissions')->insert(['profile_id' => $profileId, 'page_id' => $pageId, 'level' => $level, 'created_at' => now(), 'updated_at' => now()]);

        $admin = $this->adminUser();
        $admin->profile_id = $profileId; // in memory only (the test is rolled back anyway)

        return $admin;
    }

    public function test_the_new_pages_exist_hidden_where_they_need_an_id_and_on_the_profile_form(): void
    {
        $hiddenNeedsId = ['admin.polls.results', 'admin.national-council-poll.progress', 'admin.national-council-poll.votes-report', 'admin.national-council-poll.reset-votes', 'admin.internal-election.progress', 'admin.internal-election.votes-report'];
        foreach ($hiddenNeedsId as $url) {
            $page = DB::table('pages')->where('url', $url)->first();
            $this->assertNotNull($page, $url);
            $this->assertEquals(1, $page->hide, "$url cannot be a sidebar link (it needs an id)");
            $this->assertEquals(1, $page->show_in_profile_form, "$url is on the Profile form");
        }
        $board = DB::table('pages')->where('url', 'admin.national-council-poll.results-overview')->first();
        $this->assertEquals(0, $board->hide, 'the results board has no id, so it shows in the sidebar');
    }

    public function test_only_the_granted_page_of_the_group_opens(): void
    {
        $admin = $this->adminWithOnly('admin.national-council-poll.progress', 'view');

        // allowed: anything but 403 (the poll does not exist, so 404 is fine)
        $this->assertNotSame(403, $this->actingAs($admin, 'admin')->get(route('admin.national-council-poll.progress', 999999, false))->getStatusCode());

        // the siblings stay closed
        foreach ([
            route('admin.national-council-poll.results-overview', [], false),
            route('admin.national-council-poll.votes-report', 999999, false),
            route('admin.national-council-poll.reset-votes', 999999, false),
            route('admin.national-council-poll.index', [], false),
            route('admin.polls.results', 999999, false),
            route('admin.internal-election.progress', 999999, false),
        ] as $url) {
            $this->assertSame(403, $this->actingAs($admin, 'admin')->get($url)->getStatusCode(), "$url must be closed");
        }
    }

    public function test_reset_votes_needs_full_on_its_own_line(): void
    {
        $viewOnly = $this->adminWithOnly('admin.national-council-poll.reset-votes', 'view');
        $this->assertSame(403, $this->actingAs($viewOnly, 'admin')->get(route('admin.national-council-poll.reset-votes', 999999, false))->getStatusCode());

        $full = $this->adminWithOnly('admin.national-council-poll.reset-votes', 'full');
        $this->assertNotSame(403, $this->actingAs($full, 'admin')->get(route('admin.national-council-poll.reset-votes', 999999, false))->getStatusCode());
    }
}
