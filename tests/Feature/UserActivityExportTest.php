<?php

namespace Tests\Feature;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Feature\NationalCouncil\CouncilTestCase;

// User Activity dashboard: the popup of every tile can show only the NEW members and export what it shows to Excel.
// "new" = the member's very first logged activity is inside the tile's own period.
class UserActivityExportTest extends CouncilTestCase
{
    private string $today;
    private string $weekStart;

    protected function setUp(): void
    {
        parent::setUp();
        $this->today = Carbon::now(config('app.timezone'))->toDateString();
        $this->weekStart = Carbon::parse($this->today)->startOfWeek(Carbon::MONDAY)->toDateString();

        // A: first ever activity = today.  B: active today, first activity before this week.  C: active today, first activity at the start of this week.
        $this->member(5900000601, 'ZQ A new today', 'جبيل', 'عمشيت', [$this->today]);
        $this->member(5900000602, 'ZQ B old member', 'كسروان', 'فاريا', [Carbon::parse($this->weekStart)->subDays(3)->toDateString(), $this->today]);
        $this->member(5900000603, 'ZQ C new this week', 'جبيل', 'المغيرة', [$this->weekStart, $this->today]);
    }

    private function member(int $memberId, string $name, string $district, string $town, array $dates): void
    {
        DB::table('fpm_users')->insert([
            'MemberId' => $memberId, 'UserFullName' => $name, 'MobileNumber' => (string) $memberId, 'Email' => '', 'district' => $district, 'town' => $town,
            'LastUnitPosition' => '', 'member_status' => 1, 'BirthDate' => '1970-01-01', 'CanCreateEventRequest' => 0, 'CanCreateMeetings' => 0,
            'ElectionState' => '', 'ElectionStateId' => 0, 'ElectionStateVoltersNumber' => 0, 'Role' => '', 'RoleId' => 0, 'PersonImage' => '',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $appUser = $this->makeAppUser($name, (string) $memberId, ['verified' => 1]);
        foreach (array_unique($dates) as $d) {
            DB::table('user_activity_logs')->insert(['app_user_id' => $appUser, 'activity_date' => $d, 'created_at' => now()]);
        }
    }

    private function mine($rows)
    {
        return collect($rows)->filter(fn($r) => str_starts_with($r['name'] ?? '', 'ZQ'))->keyBy('name');
    }

    private function sheetRows($response): array
    {
        $file = $response->baseResponse->getFile()->getPathname();

        return IOFactory::load($file)->getActiveSheet()->toArray();
    }

    public function test_new_means_first_ever_activity_inside_the_tiles_own_period(): void
    {
        $this->actingAs($this->adminUser(), 'admin');

        $today = $this->mine($this->getJson('/admin/user-activity/members/today')->assertOk()->json());
        $this->assertTrue($today['ZQ A new today']['is_new']);
        $this->assertFalse($today['ZQ B old member']['is_new']);
        // C first appeared at the start of the week: new on the Today tile only when that start IS today (a Monday)
        $this->assertSame($this->weekStart === $this->today, $today['ZQ C new this week']['is_new']);

        $week = $this->mine($this->getJson('/admin/user-activity/members/week')->assertOk()->json());
        $this->assertTrue($week['ZQ A new today']['is_new']);
        $this->assertTrue($week['ZQ C new this week']['is_new'], 'first seen this week = new on the week tile');
        $this->assertFalse($week['ZQ B old member']['is_new'], 'was already active before this week');

        // the "Active Today" tile and a single chart day keep their old meaning: first ever activity that day
        $day = $this->mine($this->getJson('/admin/user-activity/members-by-date/' . $this->today)->assertOk()->json());
        $this->assertTrue($day['ZQ A new today']['is_new']);
        $this->assertFalse($day['ZQ B old member']['is_new']);
    }

    public function test_every_tile_has_an_excel_export_that_follows_the_filters(): void
    {
        $this->actingAs($this->adminUser(), 'admin');

        foreach (['today', 'week', 'month', 'year', 'decade'] as $period) {
            $rows = $this->sheetRows($this->get('/admin/user-activity/members/' . $period . '/export')->assertOk());
            $this->assertSame(['Member ID', 'Name', 'District', 'Town', 'Last Activity', 'New'], $rows[0], "headings of $period");
            $names = collect($rows)->pluck(1);
            $this->assertTrue($names->contains('ZQ A new today'), "$period lists A");
            $this->assertTrue($names->contains('ZQ B old member'), "$period lists B");
        }

        // only new, this week: A and C but not B
        $rows = $this->sheetRows($this->get('/admin/user-activity/members/week/export?new=1')->assertOk());
        $names = collect($rows)->pluck(1)->filter(fn($n) => str_starts_with((string) $n, 'ZQ'))->values()->all();
        $this->assertEqualsCanonicalizing(['ZQ A new today', 'ZQ C new this week'], $names);

        // district + town filter
        $rows = $this->sheetRows($this->get('/admin/user-activity/members/week/export?' . http_build_query(['district' => 'جبيل', 'town' => 'عمشيت']))->assertOk());
        $names = collect($rows)->pluck(1)->filter(fn($n) => str_starts_with((string) $n, 'ZQ'))->values()->all();
        $this->assertSame(['ZQ A new today'], $names);

        // one chart day
        $rows = $this->sheetRows($this->get('/admin/user-activity/members-by-date/' . $this->today . '/export?new=1')->assertOk());
        $this->assertTrue(collect($rows)->pluck(1)->contains('ZQ A new today'));
        $this->assertFalse(collect($rows)->pluck(1)->contains('ZQ B old member'));
    }

    public function test_bad_period_or_date_is_refused_and_a_guest_is_sent_away(): void
    {
        $this->actingAs($this->adminUser(), 'admin');
        $this->get('/admin/user-activity/members/forever/export')->assertNotFound();
        $this->get('/admin/user-activity/members-by-date/not-a-date/export')->assertNotFound();

        auth('admin')->logout();
        $this->get('/admin/user-activity/members/today/export')->assertRedirect();
    }

    public function test_the_popup_has_the_only_new_filter_and_the_export_button(): void
    {
        $html = $this->actingAs($this->adminUser(), 'admin')->get('/admin/user-activity')->assertOk()->getContent();
        $this->assertStringContainsString('id="activityOnlyNew"', $html);
        $this->assertStringContainsString('id="activityExportBtn"', $html);
        foreach (['today', 'week', 'month', 'year', 'decade'] as $period) {
            $this->assertStringContainsString("openActivityModal('$period'", $html, "tile $period");
        }
    }
}
