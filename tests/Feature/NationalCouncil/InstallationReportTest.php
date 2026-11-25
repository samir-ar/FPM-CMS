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
        $this->member(5900000701, 'ZR Town A', 'ZR Sect A', 'ZR Unit A');
        $this->member(5900000702, 'ZR Town A', 'ZR Sect B', 'ZR Unit B');
        $this->member(5900000703, 'ZR Town B', 'ZR Sect A', 'ZR Unit A');

        // 701 installed in October and active in November; 702 installed in November, never active; 703 never installed
        $u1 = $this->makeAppUser('ZR 701', '5900000701', ['verified' => 1]);
        DB::table('app_users')->where('id', $u1)->update(['created_at' => '2026-10-10 08:00:00']);
        DB::table('user_activity_logs')->insert(['app_user_id' => $u1, 'activity_date' => '2026-11-05', 'created_at' => '2026-11-05 09:00:00']);
        $u2 = $this->makeAppUser('ZR 702', '5900000702', ['verified' => 1]);
        DB::table('app_users')->where('id', $u2)->update(['created_at' => '2026-11-12 08:00:00']);

        $filter = ['town' => 'ZR Town', 'sect' => '', 'unit' => '', 'member_id' => ''];

        $oct = $this->row((new AppInstallationReportExport(2026, 10, $filter))->buildRows());
        $this->assertSame(3, $oct['total_member']);
        $this->assertSame(1, $oct['total_installed'], 'only 701 existed by the end of October');
        $this->assertSame(0, $oct['total_active'], 'nobody opened the app in October');

        $nov = $this->row((new AppInstallationReportExport(2026, 11, $filter))->buildRows());
        $this->assertSame(2, $nov['total_installed']);
        $this->assertSame(1, $nov['total_active']);
        $this->assertEquals(66.67, $nov['percentage']);
        $this->assertEquals(33.33, $nov['active_percentage']);
    }

    public function test_the_filters_narrow_the_members(): void
    {
        $this->member(5900000711, 'ZR Town A', 'ZR Sect A', 'ZR Unit A');
        $this->member(5900000712, 'ZR Town B', 'ZR Sect B', 'ZR Unit B');

        $base = ['district' => '', 'town' => 'ZR Town', 'sect' => '', 'unit' => '', 'member_id' => ''];
        $count = fn(array $f) => $this->row((new AppInstallationReportExport(2026, 11, array_merge($base, $f)))->buildRows())['total_member'];

        $this->assertSame(2, $count([]));
        $this->assertSame(1, $count(['town' => 'ZR Town B']));
        $this->assertSame(1, $count(['sect' => 'ZR Sect A']));
        $this->assertSame(1, $count(['unit' => 'ZR Unit B']));
        $this->assertSame(1, $count(['member_id' => '5900000711']));
        $this->assertSame(0, $count(['member_id' => '5900000999']));

        // the district filter keeps only that district's row
        $rows = (new AppInstallationReportExport(2026, 11, array_merge($base, ['district' => 'جبيل'])))->buildRows();
        $this->assertCount(1, $rows);
    }

    public function test_the_page_and_the_excel_download(): void
    {
        $this->member(5900000721, 'ZR Town A', 'ZR Sect A', 'ZR Unit A');
        $this->actingAs($this->adminUser(), 'admin');

        $html = $this->get('/admin/users/installation-report?year=2026&month=11&member_id=5900000721')->assertOk()->getContent();
        $this->assertStringContainsString('Installation Report - November 2026', $html);
        $this->assertStringContainsString('total_active_percentage', $html);

        // before tracking began = clamped to September 2026; the future = clamped to this month
        $this->get('/admin/users/installation-report?year=2020&month=1')->assertOk()->assertSee('Installation Report - September 2026');

        $response = $this->get('/admin/users/installation-report/download?year=2026&month=11&member_id=5900000721')->assertOk();
        $this->assertStringContainsString('app_installation_report_2026-11.xlsx', (string) $response->headers->get('content-disposition'));

        $tmp = tempnam(sys_get_temp_dir(), 'ir') . '.xlsx';
        file_put_contents($tmp, $response->streamedContent());
        $sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($tmp)->getActiveSheet();
        @unlink($tmp);
        $this->assertSame('Nov 2026', $sheet->getCell('B3')->getValue());
        $this->assertSame('total_active', $sheet->getCell('E4')->getValue());
        $this->assertSame('total_active_percentage', $sheet->getCell('F4')->getValue());
        $this->assertSame('Table Number 2', $sheet->getCell('H1')->getValue(), 'the second table starts after the first one');
    }

    public function test_the_users_page_button_opens_the_report(): void
    {
        $this->actingAs($this->adminUser(), 'admin');
        $this->get('/admin/users')->assertOk()->assertSee('/admin/users/installation-report', false);
    }
}
