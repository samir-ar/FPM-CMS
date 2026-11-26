<?php

namespace Tests\Feature\NationalCouncil;

use Illuminate\Support\Facades\DB;

// A display account with ONE permission, "Screens": it opens the live attendance board, the live voting screen and
// the results board, and nothing else. Needs migration 2026_11_26_150000.
class CouncilScreensAccountTest extends CouncilTestCase
{
    private int $poll;
    private int $meeting;

    protected function setUp(): void
    {
        parent::setUp();
        $now = now();
        $this->poll = DB::table('council_national_polls')->insertGetId(['title' => 'T screens poll', 'is_published' => 1, 'show_in_results' => 1, 'created_at' => $now, 'updated_at' => $now]);
        $this->meeting = DB::table('council_meetings')->insertGetId(['title' => 'T screens meeting', 'meeting_date' => $now->toDateString(), 'member_count' => 0, 'total_weight' => 0,
            'quorum_majority' => 1, 'quorum_two_thirds' => 1, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
    }

    private function account(): \App\User
    {
        $pageId = DB::table('pages')->where('url', 'admin.council-screens.index')->value('id');
        $this->assertNotNull($pageId, 'run the Screens page migration first');
        $profileId = DB::table('profiles')->insertGetId(['name' => 'T screens ' . uniqid(), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('profile_permissions')->insert(['profile_id' => $profileId, 'page_id' => $pageId, 'level' => 'view', 'created_at' => now(), 'updated_at' => now()]);
        $admin = $this->adminUser();
        $admin->profile_id = $profileId; // in memory only

        return $admin;
    }

    public function test_it_lands_on_screens_and_sees_only_that_menu_line(): void
    {
        $admin = $this->account();
        $this->actingAs($admin, 'admin');

        $this->assertSame(route('admin.council-screens.index'), $admin->firstAccessibleUrl());
        $html = $this->get(route('admin.council-screens.index', [], false))->assertOk()->getContent();
        foreach (['Live attendance', 'Live voting', 'Results board', 'T screens poll', 'T screens meeting'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        $this->assertStringContainsString(route('admin.council-meetings.board', $this->meeting), $html);
        $this->assertStringContainsString(route('admin.national-council-poll.board', $this->poll), $html);
        foreach (['APP USERS', 'Council Meetings', 'National Council Poll', 'Administration'] as $menu) {
            $this->assertStringNotContainsString('<span>' . $menu, $html, "$menu is not in the menu");
        }
    }

    public function test_the_three_screens_open_and_come_back_to_screens(): void
    {
        $this->actingAs($this->account(), 'admin');
        $back = 'href="' . route('admin.council-screens.index') . '"';

        foreach ([
            route('admin.council-meetings.board', $this->meeting, false),
            route('admin.national-council-poll.board', $this->poll, false),
            route('admin.national-council-poll.results-overview', [], false),
        ] as $url) {
            $this->assertStringContainsString($back, $this->get($url)->assertOk()->getContent(), $url);
        }
        $this->get(route('admin.council-meetings.board-data', $this->meeting, false))->assertOk();
        $this->get(route('admin.national-council-poll.board-data', $this->poll, false))->assertOk();
        $this->get(route('admin.national-council-poll.results-overview.data', [], false) . '?polls[]=' . $this->poll)->assertOk();
    }

    public function test_everything_else_is_closed(): void
    {
        $this->actingAs($this->account(), 'admin');

        foreach ([
            route('admin.national-council-poll.index', [], false),
            route('admin.national-council-poll.progress', $this->poll, false),
            route('admin.national-council-poll.progress.export', $this->poll, false),
            route('admin.national-council-poll.votes-report', $this->poll, false),
            route('admin.national-council-poll.reset-votes', $this->poll, false),
            route('admin.national-council-poll.toggle-publish', $this->poll, false),
            route('admin.national-council-poll.results', $this->poll, false),
            route('admin.council-meetings.index', [], false),
            route('admin.council-meetings.show', $this->meeting, false),
            route('admin.council-meetings.attendance.export', $this->meeting, false),
            route('admin.council-roster.index', [], false),
            route('admin.users.index', [], false),
            route('admin.users.export', [], false),
            route('admin.profiles.index', [], false),
            route('admin.admins.index', [], false),
            route('admin.internal-election.index', [], false),
            route('admin.polls.index', [], false),
            route('admin.bulkpushnotification.index', [], false),
        ] as $url) {
            $this->assertSame(403, $this->get($url)->getStatusCode(), "$url must be closed");
        }
    }

    public function test_the_usual_back_buttons_stay_for_an_account_with_the_usual_pages(): void
    {
        $this->actingAs($this->adminUser(), 'admin');
        $html = $this->get(route('admin.national-council-poll.board', $this->poll, false))->assertOk()->getContent();
        $this->assertStringContainsString('href="' . route('admin.national-council-poll.progress', $this->poll) . '"', $html);
    }
}
