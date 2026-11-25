<?php

namespace Tests\Feature\NationalCouncil;

use Illuminate\Support\Facades\DB;

// CMS > App Users: Last Activity (Beirut time), Mobile (readable phone name) and App version per account.
class AppUsersListColumnsTest extends CouncilTestCase
{
    public function test_the_users_list_shows_last_activity_mobile_and_app_version(): void
    {
        $member = $this->sampleMemberIds(1)[0];
        DB::table('app_users')->where('member_id', $member)->whereNull('deleted_at')->update(['deleted_at' => now()]);
        $id = $this->makeAppUser('T users list', $member, [
            'verified' => 1, 'app_build' => 230040143, 'device_model' => 'Apple iPhone18,2, iOS 27.2.1',
        ]);
        // stored in UTC: 10:00 UTC is 12:00 in Beirut in winter (config app.timezone)
        DB::table('user_activity_logs')->insert(['app_user_id' => $id, 'activity_date' => '2026-01-15', 'created_at' => '2026-01-15 10:00:00']);
        DB::table('user_activity_logs')->insert(['app_user_id' => $id, 'activity_date' => '2026-01-14', 'created_at' => '2026-01-14 08:00:00']);

        $this->actingAs($this->adminUser(), 'admin');
        $json = $this->getJson('/admin/users?draw=1&start=0&length=10&columns[0][data]=id&columns[0][name]=id&columns[0][orderable]=true&order[0][column]=0&order[0][dir]=desc', ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()->json();

        $row = collect($json['data'])->firstWhere('id', $id);
        $this->assertNotNull($row, 'the account is in the list');
        $this->assertSame(\Carbon\Carbon::parse('2026-01-15 10:00:00', 'UTC')->setTimezone(config('app.timezone'))->format('Y-m-d H:i'), $row['last_activity']);
        $this->assertSame('Apple iPhone 17 Pro Max, iOS 27.2.1', $row['mobile']);
        $this->assertSame('4.4.24 (230040143)', $row['app_version']);

        $html = $this->get('/admin/users')->assertOk()->getContent();
        foreach (['Last Activity', 'Mobile', 'App version'] as $h) {
            $this->assertStringContainsString($h, $html);
        }
    }

    public function test_an_account_that_never_opened_the_app_has_empty_cells(): void
    {
        $member = $this->sampleMemberIds(1)[0];
        DB::table('app_users')->where('member_id', $member)->whereNull('deleted_at')->update(['deleted_at' => now()]);
        $id = $this->makeAppUser('T users blank', $member, ['verified' => 1]);

        $this->actingAs($this->adminUser(), 'admin');
        $json = $this->getJson('/admin/users?draw=1&start=0&length=10&columns[0][data]=id&columns[0][name]=id&columns[0][orderable]=true&order[0][column]=0&order[0][dir]=desc', ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()->json();

        $row = collect($json['data'])->firstWhere('id', $id);
        $this->assertSame('', $row['last_activity']);
        $this->assertSame('', $row['app_version']);
    }
}
