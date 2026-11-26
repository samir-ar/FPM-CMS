<?php

namespace Tests\Feature\NationalCouncil;

use Illuminate\Support\Facades\DB;

// A "display only" CMS account: National Council (View) + National Council Poll (View) + Who Voted (View) +
// Council Poll Results (View). It sees who voted, the TV screen and the results board, and can change nothing.
class DisplayOnlyProfileTest extends CouncilTestCase
{
    private function displayAccount(): \App\User
    {
        $profileId = DB::table('profiles')->insertGetId(['name' => 'T display ' . uniqid(), 'created_at' => now(), 'updated_at' => now()]);
        $ids = [
            DB::table('pages')->where('name', 'National Council')->whereNull('parent_id')->value('id'),
            DB::table('pages')->where('url', 'admin.national-council-poll.index')->value('id'),
            DB::table('pages')->where('url', 'admin.national-council-poll.progress')->value('id'),
            DB::table('pages')->where('url', 'admin.national-council-poll.results-overview')->value('id'),
        ];
        foreach ($ids as $id) {
            $this->assertNotNull($id);
            DB::table('profile_permissions')->insert(['profile_id' => $profileId, 'page_id' => $id, 'level' => 'view', 'created_at' => now(), 'updated_at' => now()]);
        }
        $admin = $this->adminUser();
        $admin->profile_id = $profileId; // in memory only

        return $admin;
    }

    public function test_it_sees_who_voted_the_tv_screen_and_the_results_and_nothing_else(): void
    {
        $poll = DB::table('council_national_polls')->insertGetId(['title' => 'T display poll', 'is_published' => 1, 'show_in_results' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $admin = $this->displayAccount();
        $this->actingAs($admin, 'admin');

        $this->assertSame(route('admin.national-council-poll.index'), $admin->firstAccessibleUrl(), 'login lands on the polls list');
        foreach ([
            'admin.national-council-poll.index' => [], 'admin.national-council-poll.progress' => [$poll], 'admin.national-council-poll.board' => [$poll],
            'admin.national-council-poll.board-data' => [$poll], 'admin.national-council-poll.results-overview' => [],
        ] as $name => $args) {
            $this->get(route($name, $args, false))->assertOk();
        }

        foreach ([
            route('admin.national-council-poll.votes-report', $poll, false),
            route('admin.national-council-poll.reset-votes', $poll, false),
            route('admin.national-council-poll.toggle-publish', $poll, false),
            route('admin.national-council-poll.create', [], false),
            route('admin.national-council-poll.edit', $poll, false),
            route('admin.users.index', [], false),
            route('admin.users.export', [], false),
            route('admin.profiles.index', [], false),
            route('admin.admins.index', [], false),
            route('admin.council-meetings.index', [], false),
            route('admin.internal-election.index', [], false),
            route('admin.polls.index', [], false),
            route('admin.bulkpushnotification.index', [], false),
        ] as $url) {
            $this->assertSame(403, $this->get($url)->getStatusCode(), "$url must be closed");
        }
        $this->post(route('admin.national-council-poll.closes-at', $poll, false), ['closes_at' => '2027-01-01T10:00'])->assertForbidden();
        $this->post(route('admin.national-council-poll.show-in-results', $poll, false), ['show' => 0])->assertForbidden();
        $this->delete(route('admin.national-council-poll.destroy', $poll, false))->assertForbidden();
        $this->assertSame(1, (int) DB::table('council_national_polls')->where('id', $poll)->value('is_published'));
    }
}
