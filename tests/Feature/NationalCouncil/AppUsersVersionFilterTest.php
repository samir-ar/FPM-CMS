<?php

namespace Tests\Feature\NationalCouncil;

use App\Exports\AppInstallationReportExport;
use Illuminate\Support\Facades\DB;

// CMS > App Users: the App version filter (table, drop-down, chips, Installation Report) and the readable cells.
class AppUsersVersionFilterTest extends CouncilTestCase
{
    /** Three accounts: on build 230040143, on build 230040142, and one whose version is not known. */
    private function accounts(): array
    {
        DB::table('app_users')->whereNull('deleted_at')->update(['deleted_at' => now()]);
        $members = ['5900000801', '5900000802', '5900000803'];
        foreach ($members as $id) {
            DB::table('fpm_users')->insert([
                'MemberId' => $id, 'UserFullName' => 'ZR ' . $id, 'MobileNumber' => $id, 'Email' => '', 'district' => 'جبيل', 'town' => 'ZR Version Town', 'sect' => '',
                'NashatUnit' => '', 'LastUnitPosition' => '', 'member_status' => 1, 'BirthDate' => '1970-01-01', 'CanCreateEventRequest' => 0, 'CanCreateMeetings' => 0,
                'ElectionState' => '', 'ElectionStateId' => 0, 'ElectionStateVoltersNumber' => 0, 'Role' => '', 'RoleId' => 0, 'PersonImage' => '',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return [
            $this->makeAppUser('ZR new', $members[0], ['verified' => 1, 'app_build' => 230040143]),
            $this->makeAppUser('ZR old', $members[1], ['verified' => 1, 'app_build' => 230040142]),
            $this->makeAppUser('ZR unknown', $members[2], ['verified' => 0]),
        ];
    }

    private function table(string $query): array
    {
        $url = '/admin/users?draw=1&start=0&length=100&columns[0][data]=id&columns[0][name]=id&columns[0][orderable]=true&order[0][column]=0&order[0][dir]=asc' . $query;
        $this->app->forgetInstance('datatables.request');

        return $this->getJson($url, ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()->json('data');
    }

    public function test_the_table_is_filtered_by_app_version(): void
    {
        [$new, $old, $unknown] = $this->accounts();
        $this->actingAs($this->adminUser(), 'admin');

        $this->assertSame([$new, $old, $unknown], array_column($this->table(''), 'id'));
        $this->assertSame([$new], array_column($this->table('&f_build=230040143'), 'id'));
        $this->assertSame([$old], array_column($this->table('&f_build=230040142'), 'id'));
        $this->assertSame([$unknown], array_column($this->table('&f_build=none'), 'id'));
        $this->assertSame([$new, $old, $unknown], array_column($this->table('&f_build=abc'), 'id'), 'nonsense is ignored');
        $this->assertSame([$new], array_column($this->table('&f_build=230040143&f_town=ZR Version Town'), 'id'));
        $this->assertSame([], $this->table('&f_build=230040143&f_member=5900000802'));
    }

    public function test_the_page_lists_the_versions_with_counts_and_shows_what_is_filtered(): void
    {
        $this->accounts();
        $this->actingAs($this->adminUser(), 'admin');

        $html = $this->get('/admin/users')->assertOk()->getContent();
        $this->assertStringContainsString('4.4.24 (230040143) - 1', $html);
        $this->assertStringContainsString('4.4.23 (230040142) - 1', $html);
        $this->assertStringContainsString('Not known yet - 1', $html);
        $this->assertStringNotContainsString('Filtered by:', $html);

        $html = $this->get('/admin/users?f_build=230040143&f_district=' . urlencode('جبيل') . '&f_town=' . urlencode('ZR Version Town'))->assertOk()->getContent();
        $this->assertStringContainsString('Filtered by:', $html);
        $this->assertStringContainsString('App version: <bdi>4.4.24 (230040143)</bdi>', $html);
        $this->assertStringContainsString('<input type="hidden" name="f_build" value="230040143">', $html, 'the report takes the filter along');
        // removing the district chip also removes the town under it, and keeps the version
        preg_match('/<a href="([^"]*)" title="Remove this filter" aria-label="Remove District filter">/', $html, $m);
        $this->assertStringContainsString('f_build=230040143', $m[1]);
        $this->assertStringNotContainsString('f_town', $m[1]);
        $this->assertStringNotContainsString('f_district', $m[1]);
    }

    public function test_the_report_counts_only_that_version_as_installed(): void
    {
        $this->accounts();
        DB::table('app_users')->whereNull('deleted_at')->update(['created_at' => '2026-10-10 08:00:00']);
        foreach (DB::table('app_users')->whereNull('deleted_at')->pluck('id') as $id) {
            DB::table('user_activity_logs')->insert(['app_user_id' => $id, 'activity_date' => '2026-10-11', 'created_at' => '2026-10-11 09:00:00']);
        }

        $row = fn(string $build) => collect((new AppInstallationReportExport(2026, 10, ['town' => 'ZR Version Town', 'app_build' => $build]))->buildRows())->firstWhere('kada', 'جبيل');

        $this->assertSame([2, 3, 3], [$row('')['total_installed'], $row('')['total_member'], $row('')['total_active']]);
        $this->assertSame([1, 3, 1], [$row('230040143')['total_installed'], $row('230040143')['total_member'], $row('230040143')['total_active']]);
        $this->assertSame([0, 3, 1], [$row('none')['total_installed'], $row('none')['total_member'], $row('none')['total_active']]);
    }

    public function test_registration_date_and_verified_are_readable(): void
    {
        [$new] = $this->accounts();
        DB::table('app_users')->where('id', $new)->update(['created_at' => '2026-11-22 19:37:25']);
        $this->actingAs($this->adminUser(), 'admin');

        $row = $this->table('&f_build=230040143')[0];
        $this->assertStringStartsWith('2026-11-22 19:37 <span', $row['created_at']);
        $this->assertStringContainsString('>Yes</span>', $row['verified']);
    }
}
