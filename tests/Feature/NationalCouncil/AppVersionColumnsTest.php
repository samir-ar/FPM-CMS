<?php

namespace Tests\Feature\NationalCouncil;

use App\Support\MemberApp;
use App\V2\AppUser;
use App\V2\CouncilRosterMember;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// The app version (and phone) of a member: kept by the API middleware, shown in the council roster, the council votes
// report and the User Activity popups (+ their Excel files).
class AppVersionColumnsTest extends CouncilTestCase
{
    private string $memberId;
    private int $appUserId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->memberId = $this->sampleMemberIds(1)[0];
        DB::table('app_users')->where('member_id', $this->memberId)->whereNull('deleted_at')->update(['deleted_at' => now()]);
        $this->appUserId = $this->makeAppUser('T app version', $this->memberId, [
            'verified' => 1, 'app_build' => 230040143, 'app_build_seen_at' => now(), 'device_model' => 'Apple iPhone18,2, iOS 27.2.1',
        ]);
        DB::table('user_activity_logs')->insert(['app_user_id' => $this->appUserId, 'activity_date' => now(config('app.timezone'))->toDateString(), 'created_at' => now()]);
    }

    public function test_version_label()
    {
        $this->assertSame('4.4.24 (230040143)', MemberApp::versionLabel(230040143));
        $this->assertSame('4.4.16 (230040135)', MemberApp::versionLabel(230040135));
        $this->assertSame('build 220000001', MemberApp::versionLabel(220000001));
        $this->assertSame('', MemberApp::versionLabel(null));
        $this->assertSame('', MemberApp::versionLabel(0));
    }

    public function test_member_app_gives_version_and_the_phone_name()
    {
        $info = MemberApp::forMembers([$this->memberId, '999999999999']);
        $this->assertSame('4.4.24 (230040143)', $info[$this->memberId]['version']);
        $this->assertSame('Apple iPhone 17 Pro Max, iOS 27.2.1', $info[$this->memberId]['model']);
        $this->assertArrayNotHasKey('999999999999', $info);
    }

    public function test_the_middleware_trait_records_build_and_phone_only_when_they_change()
    {
        DB::table('app_users')->where('id', $this->appUserId)->update(['app_build' => null, 'app_build_seen_at' => null, 'device_model' => null]);
        $h = new class {
            use \App\Http\Traits\TracksUserActivityTrait;

            public function go($u, $r)
            {
                $this->recordAppInfo($u, $r);
            }
        };

        $h->go(AppUser::find($this->appUserId), Request::create('/x', 'POST', ['build_number' => '230040142']));
        $row = DB::table('app_users')->find($this->appUserId);
        $this->assertSame(230040142, (int) $row->app_build);
        $this->assertNotNull($row->app_build_seen_at);
        $this->assertNull($row->device_model, 'no phone sent = none stored');

        $h->go(AppUser::find($this->appUserId), Request::create('/x', 'POST', ['build_number' => '230040143', 'device_model' => 'Apple iPhone18,3, iOS 26.1']));
        $row = DB::table('app_users')->find($this->appUserId);
        $this->assertSame(230040143, (int) $row->app_build);
        $this->assertSame('Apple iPhone18,3, iOS 26.1', $row->device_model);

        // nonsense or missing build numbers never overwrite a good one
        foreach (['0', 'abc', '', '99999999999'] as $bad) {
            $h->go(AppUser::find($this->appUserId), Request::create('/x', 'POST', ['build_number' => $bad]));
        }
        $h->go(AppUser::find($this->appUserId), Request::create('/x', 'GET'));
        $this->assertSame(230040143, (int) DB::table('app_users')->find($this->appUserId)->app_build);
    }

    public function test_the_roster_shows_it()
    {
        CouncilRosterMember::whereNotNull('id')->delete();
        CouncilRosterMember::create(['member_id' => $this->memberId, 'weight' => 5]);
        $this->actingAs($this->adminUser(), 'admin');

        $html = $this->get('/admin/council-roster')->assertOk()->getContent();
        $this->assertStringContainsString('App 4.4.24 (230040143)', $html);
        $this->assertStringContainsString('Apple iPhone 17 Pro Max, iOS 27.2.1', $html);
    }

    public function test_the_roster_can_be_filtered_by_logged_in_or_not()
    {
        $ids = $this->sampleMemberIds(40);
        $seen = \App\Support\CouncilMemberLookup::activitySince($ids)['last_seen'];
        $other = collect($ids)->first(fn($id) => $id !== (string) $this->memberId && !isset($seen[$id]));
        CouncilRosterMember::whereNotNull('id')->delete();
        CouncilRosterMember::create(['member_id' => $this->memberId, 'weight' => 5]);
        CouncilRosterMember::create(['member_id' => $other, 'weight' => 1]);
        $this->actingAs($this->adminUser(), 'admin');

        $html = $this->get('/admin/council-roster')->assertOk()->getContent();

        $this->assertStringContainsString('id="filterApp"', $html);
        $this->assertStringContainsString('value="in">Logged in<', $html);
        $this->assertStringContainsString('value="no">Not logged in yet<', $html);
        // every row carries the same answer as its App column
        preg_match_all('/data-app="(in|no)".*?(logged in|not logged in yet)<\/span>/s', $html, $m, PREG_SET_ORDER);
        $this->assertCount(2, $m);
        foreach ($m as $row) {
            $this->assertSame($row[1] === 'in' ? 'logged in' : 'not logged in yet', $row[2]);
        }
    }

    public function test_the_user_activity_popup_and_its_excel_have_mobile_and_app_version()
    {
        $this->actingAs($this->adminUser(), 'admin');

        $mine = collect($this->getJson('/admin/user-activity/members/today')->assertOk()->json())->firstWhere('member_id', $this->memberId);
        $this->assertSame('Apple iPhone 17 Pro Max, iOS 27.2.1', $mine['mobile']);
        $this->assertSame('4.4.24 (230040143)', $mine['app_version']);

        $file = $this->get('/admin/user-activity/members/today/export')->assertOk()->baseResponse->getFile()->getPathname();
        $rows = \PhpOffice\PhpSpreadsheet\IOFactory::load($file)->getActiveSheet()->toArray();
        $line = collect($rows)->first(fn($r) => (string) $r[0] === $this->memberId);
        $this->assertSame('Apple iPhone 17 Pro Max, iOS 27.2.1', $line[4]);
        $this->assertSame('4.4.24 (230040143)', $line[5]);
    }
}
