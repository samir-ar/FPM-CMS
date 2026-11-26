<?php

namespace Tests\Feature\NationalCouncil;

use App\Exports\AppInstallationReportExport;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

// Installation Report: pick a month + year (from Sept 2026), filter by district / town / sect / activity unit /
// member id; installed = verified account created by the end of the month, active = opened the app that month.
class InstallationReportTest extends CouncilTestCase
{
    private function member(int $id, string $town, string $sect, string $unit): void
    {
        DB::table('fpm_users')->insert([
            'MemberId' => $id, 'UserFullName' => 'ZR ' . $id, 'MobileNumber' => (string) $id, 'Email' => '', 'district' => 'جبيل', 'town' => $town, 'sect' => $sect,
            'NashatUnit' => $unit, 'LastUnitPosition' => '', 'member_status' => 1, 'BirthDate' => '1970-01-01', 'CanCreateEventRequest' => 0, 'CanCreateMeetings' => 0,
            'ElectionState' => '', 'ElectionStateId' => 0, 'ElectionStateVoltersNumber' => 0, 'Role' => '', 'RoleId' => 0, 'PersonImage' => '',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function row(array $rows): array
    {
        return collect($rows)->firstWhere('kada', 'جبيل');
    }

    public function test_installed_by_the_end_of_the_month_and_active_in_the_month(): void
    {
        $this->member(5900000701, 'ZR Town', 'ZR Sect A', 'ZR Unit A');
        $this->member(5900000702, 'ZR Town', 'ZR Sect B', 'ZR Unit B');
        $this->member(5900000703, 'ZR Town', 'ZR Sect A', 'ZR Unit A');
        // 704 installed BEFORE tracking began (24 Sept): not counted as an install of this report
        $this->member(5900000704, 'ZR Town', 'ZR Sect B', 'ZR Unit B');
        $u4 = $this->makeAppUser('ZR 704', '5900000704', ['verified' => 1]);
        DB::table('app_users')->where('id', $u4)->update(['created_at' => '2026-09-10 08:00:00']);

        // 701 installed in October and active in November; 702 installed in November, never active; 703 never installed
        $u1 = $this->makeAppUser('ZR 701', '5900000701', ['verified' => 1]);
        DB::table('app_users')->where('id', $u1)->update(['created_at' => '2026-10-10 08:00:00']);
        DB::table('user_activity_logs')->insert(['app_user_id' => $u1, 'activity_date' => '2026-11-05', 'created_at' => '2026-11-05 09:00:00']);
        $u2 = $this->makeAppUser('ZR 702', '5900000702', ['verified' => 1]);
        DB::table('app_users')->where('id', $u2)->update(['created_at' => '2026-11-12 08:00:00']);
        // 702 opened the app once, on 26 September (before it was replaced by a newer account in November)
        DB::table('user_activity_logs')->insert(['app_user_id' => $u2, 'activity_date' => '2026-09-26', 'created_at' => '2026-09-26 09:00:00']);

        $filter = ['town' => 'ZR Town', 'sect' => '', 'unit' => '', 'member_id' => ''];

        $sep = $this->row((new AppInstallationReportExport(2026, 9, $filter))->buildRows());
        $this->assertSame(1, $sep['total_active'], 'September counts from the 24th: 702 opened the app on the 26th');
        $this->assertSame(0, $sep['total_installed']);

        $oct = $this->row((new AppInstallationReportExport(2026, 10, $filter))->buildRows());
        $this->assertSame(4, $oct['total_member']);
        $this->assertSame(1, $oct['total_installed'], 'only 701 is an install since 24 Sept by the end of October (704 is older)');
        $this->assertSame(1, $oct['total_active'], 'a running total from 24 Sept: 702 (opened in September) still counts in October');

        $nov = $this->row((new AppInstallationReportExport(2026, 11, $filter))->buildRows());
        $this->assertSame(2, $nov['total_installed']);
        $this->assertSame(2, $nov['total_active'], '702 (September) and 701 (November), each member once');
        $this->assertEquals(50, $nov['percentage']);
        $this->assertEquals(50, $nov['active_percentage']);
    }

    public function test_the_filters_narrow_the_members(): void
    {
        $this->member(5900000711, 'ZR Town A', 'ZR Sect A', 'ZR Unit A');
        $this->member(5900000712, 'ZR Town B', 'ZR Sect B', 'ZR Unit B');

        $base = ['district' => '', 'town' => '', 'sect' => '', 'unit' => '', 'member_id' => ''];
        $count = fn(array $f) => $this->row((new AppInstallationReportExport(2026, 11, array_merge($base, $f)))->buildRows())['total_member'];

        $this->assertSame(1, $count(['town' => 'ZR Town B']));
        $this->assertSame(0, $count(['town' => 'ZR Town']), 'a drop-down value matches exactly, not partly');
        $this->assertSame(1, $count(['sect' => 'ZR Sect A']));
        $this->assertSame(1, $count(['unit' => 'ZR Unit B']));
        $this->assertSame(1, $count(['member_id' => '5900000711']));
        $this->assertSame(0, $count(['member_id' => '5900000999']));

        // the district filter keeps only that district's row
        $rows = (new AppInstallationReportExport(2026, 11, array_merge($base, ['district' => 'جبيل'])))->buildRows();
        $this->assertCount(1, $rows, 'one district asked for = one row, no "Not in any district" row');
    }

    public function test_the_page_and_the_excel_download(): void
    {
        $this->member(5900000721, 'ZR Town A', 'ZR Sect A', 'ZR Unit A');
        $this->actingAs($this->adminUser(), 'admin');

        // before tracking began = clamped to September 2026
        $early = $this->get('/admin/users/installation-report?period=2020-01')->assertOk();
        $this->assertStringContainsString('app_installation_report_2026-09.xlsx', (string) $early->headers->get('content-disposition'));

        $response = $this->get('/admin/users/installation-report?period=2026-11&f_member=5900000721')->assertOk();
        $this->assertStringContainsString('app_installation_report_2026-11.xlsx', (string) $response->headers->get('content-disposition'));

        $tmp = tempnam(sys_get_temp_dir(), 'ir') . '.xlsx';
        file_put_contents($tmp, $response->streamedContent());
        $sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($tmp)->getActiveSheet();
        @unlink($tmp);
        $this->assertSame('24 Sep - 30 Nov 2026', $sheet->getCell('B3')->getValue());
        $this->assertSame('total_active', $sheet->getCell('E4')->getValue());
        $this->assertSame('total_active_percentage', $sheet->getCell('F4')->getValue());
        $this->assertSame('Table Number 2', $sheet->getCell('H1')->getValue(), 'the second table starts after the first one');
    }

    public function test_the_users_page_has_the_month_list_and_the_filters(): void
    {
        $this->actingAs($this->adminUser(), 'admin');
        $html = $this->get('/admin/users?f_town=ZR')->assertOk()->getContent();
        $this->assertStringContainsString('name="period"', $html);
        $this->assertStringContainsString('September 2026', $html);
        foreach (['f_district', 'f_town', 'f_sect', 'f_unit', 'f_member'] as $name) {
            $this->assertStringContainsString("name=\"$name\"", $html);
        }
        $this->assertStringContainsString('f_town=ZR', $html, 'the table asks for the filtered rows');
    }

    public function test_each_drop_down_only_offers_what_is_under_the_ones_before_it(): void
    {
        $this->member(5900000761, 'ZR Town Jbeil', 'ZR Sect J', 'ZR Unit J');      // district جبيل
        $this->member(5900000762, 'ZR Town Other', 'ZR Sect O', 'ZR Unit O');
        DB::table('fpm_users')->where('MemberId', 5900000762)->update(['district' => 'كسروان']);
        $this->member(5900000763, 'ZR Town Jbeil', 'ZR Sect J2', 'ZR Unit J2');    // same town, another sect

        $this->actingAs($this->adminUser(), 'admin');

        // nothing chosen: everything is offered
        $all = $this->get('/admin/users')->assertOk()->getContent();
        foreach (['ZR Town Jbeil', 'ZR Town Other', 'ZR Sect J', 'ZR Sect O', 'ZR Unit J', 'ZR Unit O'] as $v) {
            $this->assertStringContainsString("value=\"$v\"", $all);
        }

        // district chosen: only its towns, sects and units
        $d = $this->get('/admin/users?f_district=' . urlencode('جبيل'))->assertOk()->getContent();
        $this->assertStringContainsString('value="ZR Town Jbeil"', $d);
        $this->assertStringNotContainsString('value="ZR Town Other"', $d);
        $this->assertStringNotContainsString('value="ZR Sect O"', $d);

        // district + town: only that town's sects and units
        $t = $this->get('/admin/users?f_district=' . urlencode('جبيل') . '&f_town=' . urlencode('ZR Town Jbeil'))->assertOk()->getContent();
        $this->assertStringContainsString('value="ZR Sect J"', $t);
        $this->assertStringContainsString('value="ZR Sect J2"', $t);
        $this->assertStringNotContainsString('value="ZR Sect O"', $t);

        // district + town + sect: only that sect's units
        $s = $this->get('/admin/users?f_district=' . urlencode('جبيل') . '&f_town=' . urlencode('ZR Town Jbeil') . '&f_sect=' . urlencode('ZR Sect J'))->assertOk()->getContent();
        $this->assertStringContainsString('value="ZR Unit J"', $s);
        $this->assertStringNotContainsString('value="ZR Unit J2"', $s);

        // picking one reloads with the deeper ones cleared
        $this->assertStringContainsString('order.slice(i + 1)', $all);
    }

    public function test_the_app_users_table_is_filtered(): void
    {
        $this->member(5900000731, 'ZR Town A', 'ZR Sect A', 'ZR Unit A');
        $this->member(5900000732, 'ZR Town B', 'ZR Sect B', 'ZR Unit B');
        $a = $this->makeAppUser('ZR 731', '5900000731', ['verified' => 1]);
        $b = $this->makeAppUser('ZR 732', '5900000732', ['verified' => 1]);
        $this->actingAs($this->adminUser(), 'admin');

        $ids = function (string $query) {
            $url = '/admin/users?draw=1&start=0&length=100&columns[0][data]=id&columns[0][name]=id&columns[0][orderable]=true&order[0][column]=0&order[0][dir]=desc' . $query;

            return collect($this->getJson($url, ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()->json('data'))->pluck('id')->all();
        };

        $this->assertEqualsCanonicalizing([$a, $b], array_intersect([$a, $b], $ids('&f_district=' . urlencode('جبيل'))));
        $this->assertSame([$a], array_values(array_intersect([$a, $b], $ids('&f_town=ZR Town A'))));
        $this->assertSame([$b], array_values(array_intersect([$a, $b], $ids('&f_sect=ZR Sect B'))));
        $this->assertSame([$b], array_values(array_intersect([$a, $b], $ids('&f_unit=ZR Unit B'))));
        $this->assertSame([$a], array_values(array_intersect([$a, $b], $ids('&f_member=5900000731'))));
        $this->assertSame([$a], array_values(array_intersect([$a, $b], $ids('&f_district=' . urlencode('جبيل') . '&f_sect=ZR Sect A'))));
        $this->assertSame([], array_values(array_intersect([$a, $b], $ids('&f_district=' . urlencode('كسروان') . '&f_sect=ZR Sect A'))));
    }

    public function test_beirut_first_and_second_are_one_beirut_row(): void
    {
        foreach ([[5900000741, 'بيروت الاولى'], [5900000742, 'بيروت الثانية']] as [$id, $district]) {
            DB::table('fpm_users')->insert([
                'MemberId' => $id, 'UserFullName' => 'ZR b' . $id, 'MobileNumber' => (string) $id, 'Email' => '', 'district' => $district, 'town' => 'ZR Beirut', 'sect' => '',
                'NashatUnit' => '', 'LastUnitPosition' => '', 'member_status' => 1, 'BirthDate' => '1970-01-01', 'CanCreateEventRequest' => 0, 'CanCreateMeetings' => 0,
                'ElectionState' => '', 'ElectionStateId' => 0, 'ElectionStateVoltersNumber' => 0, 'Role' => '', 'RoleId' => 0, 'PersonImage' => '', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $rows = (new AppInstallationReportExport(2026, 11, ['town' => 'ZR Beirut']))->buildRows();
        $this->assertSame(2, collect($rows)->firstWhere('kada', 'بيروت')['total_member']);
    }

    public function test_the_report_total_equals_the_dashboard_active_count(): void
    {
        // an active member the member list does not know, and one with a blank district: both must still be in the total
        $ghost = $this->makeAppUser('ZR ghost', '5900000751', ['verified' => 1]);
        DB::table('user_activity_logs')->insert(['app_user_id' => $ghost, 'activity_date' => now(config('app.timezone'))->toDateString(), 'created_at' => now()]);
        $this->member(5900000752, 'ZR Town', 'ZR Sect', 'ZR Unit');
        DB::table('fpm_users')->where('MemberId', 5900000752)->update(['district' => '']);
        $blank = $this->makeAppUser('ZR blank', '5900000752', ['verified' => 1]);
        DB::table('user_activity_logs')->insert(['app_user_id' => $blank, 'activity_date' => now(config('app.timezone'))->toDateString(), 'created_at' => now()]);

        $now = now(config('app.timezone'));
        $monthStart = AppInstallationReportExport::TRACKING_START;
        // exactly how the User Activity dashboard counts "Active This Year" (tracking is under a year old: since 24 Sept)
        $dashboard = DB::table('user_activity_logs')
            ->join('app_users', 'app_users.id', '=', 'user_activity_logs.app_user_id')
            ->whereNull('app_users.deleted_at')
            ->where('user_activity_logs.activity_date', '>=', $monthStart)
            ->distinct('app_users.member_id')
            ->count('app_users.member_id');

        $rows = collect((new AppInstallationReportExport($now->year, $now->month, []))->buildRows());
        $this->assertSame($dashboard, (int) $rows->sum('total_active'), 'report total = dashboard');
        $this->assertGreaterThanOrEqual(2, $rows->firstWhere('kada', AppInstallationReportExport::OTHER_LABEL)['total_active']);
    }
}
