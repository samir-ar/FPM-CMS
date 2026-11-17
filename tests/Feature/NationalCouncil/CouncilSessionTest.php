<?php

namespace Tests\Feature\NationalCouncil;

use App\Exceptions\InvalidVotersFileException;
use App\Http\Controllers\Admin\CouncilMeetingsController;
use App\Http\Controllers\Admin\CouncilRosterController;
use App\Http\Controllers\Admin\SpecialMembersController;
use App\Imports\CouncilRosterImport;
use App\Support\CouncilMemberLookup;
use App\V2\CouncilMeeting;
use App\V2\CouncilMeetingAttendance;
use App\V2\CouncilRosterMember;
use App\V2\SpecialMember;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class CouncilSessionTest extends CouncilTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        auth('admin')->setUser($this->adminUser());
    }

    private function ajax(array $data = []): Request
    {
        return Request::create('/x', 'POST', $data, [], [], ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);
    }

    // ---- quorum maths ----

    public function test_quorum_figures_for_755_and_756()
    {
        $this->assertSame(378, CouncilMeeting::majorityOf(755));
        $this->assertSame(504, CouncilMeeting::twoThirdsOf(755));
        $this->assertSame(379, CouncilMeeting::majorityOf(756));
        $this->assertSame(504, CouncilMeeting::twoThirdsOf(756));
        $this->assertSame(378, CouncilMeeting::majorityOf(754));
        $this->assertSame(503, CouncilMeeting::twoThirdsOf(754));
    }

    // ---- roster import ----

    public function test_roster_import_normalises_spaced_and_zero_padded_ids_and_skips_header_and_blank_rows()
    {
        [$a, $b] = $this->sampleMemberIds(2);
        $spaced = substr($a, 0, 2) . ' ' . substr($a, 2, 3) . ' ' . substr($a, 5);

        $import = new CouncilRosterImport();
        $import->collection(collect([
            ['member_id', 'weight', 'role'],
            [$spaced, '4', 'Test role'],
            ['0' . $b, '2', ''],
            ['', '', ''],
        ]));

        $this->assertSame(2, $import->added);
        $this->assertSame(2, $import->skipped);
        $this->assertTrue(CouncilRosterMember::where('member_id', $a)->where('weight', 4)->exists());
        $this->assertTrue(CouncilRosterMember::where('member_id', $b)->exists(), 'a leading zero must be stripped');
    }

    public function test_roster_import_updates_existing_rows_and_never_removes_anyone()
    {
        [$a, $b] = $this->sampleMemberIds(2);
        CouncilRosterMember::create(['member_id' => $a, 'weight' => 1]);
        CouncilRosterMember::create(['member_id' => $b, 'weight' => 1]);

        $import = new CouncilRosterImport();
        $import->collection(collect([[$a, '5', '']]));

        $this->assertSame(1, $import->updated);
        $this->assertSame(5, (int) CouncilRosterMember::where('member_id', $a)->value('weight'));
        $this->assertTrue(CouncilRosterMember::where('member_id', $b)->exists());
    }

    public function test_roster_import_refuses_a_row_without_a_weight()
    {
        [$a] = $this->sampleMemberIds(1);

        $this->expectException(InvalidVotersFileException::class);
        (new CouncilRosterImport())->collection(collect([[$a, '', '']]));
    }

    public function test_member_id_normaliser()
    {
        $this->assertSame('100100235', CouncilMemberLookup::normalizeMemberId('01 00100235'));
        $this->assertSame('2605200129', CouncilMemberLookup::normalizeMemberId('26 052 00129'));
        $this->assertSame('', CouncilMemberLookup::normalizeMemberId(''));
    }

    // ---- meetings + attendance ----

    private function rosterOf3(): array
    {
        [$a, $b, $c] = $this->sampleMemberIds(3);
        CouncilRosterMember::create(['member_id' => $a, 'weight' => 4]);
        CouncilRosterMember::create(['member_id' => $b, 'weight' => 5]);
        CouncilRosterMember::create(['member_id' => $c, 'weight' => 1]);
        CouncilRosterMember::whereNotIn('member_id', [$a, $b, $c])->delete();

        return [$a, $b, $c];
    }

    private function newMeeting(string $title = 'T meeting'): CouncilMeeting
    {
        app(CouncilMeetingsController::class)->store(Request::create('/x', 'POST', ['title' => $title]));

        return CouncilMeeting::where('title', $title)->firstOrFail();
    }

    public function test_a_meeting_freezes_its_quorum_from_the_roster_when_it_is_created()
    {
        $this->rosterOf3();
        $m = $this->newMeeting();

        $this->assertSame(3, $m->member_count);
        $this->assertSame(10, $m->total_weight);
        $this->assertSame(6, $m->quorum_majority);
        $this->assertSame(7, $m->quorum_two_thirds);
    }

    public function test_the_quorum_follows_whatever_the_roster_adds_up_to()
    {
        // 754 = the 104 named members of the council sheet, 755 once the women's representative (weight 1) joins
        CouncilRosterMember::whereNotNull('id')->delete();
        $this->bulkRoster(104, 754);
        $before = $this->newMeeting('T before');

        $this->assertSame(104, $before->member_count);
        $this->assertSame(754, $before->total_weight);
        $this->assertSame(378, $before->quorum_majority);
        $this->assertSame(503, $before->quorum_two_thirds);

        CouncilRosterMember::create(['member_id' => '99999999991', 'weight' => 1]);
        $after = $this->newMeeting('T after');

        $this->assertSame(105, $after->member_count);
        $this->assertSame(755, $after->total_weight);
        $this->assertSame(378, $after->quorum_majority);
        $this->assertSame(504, $after->quorum_two_thirds);
    }

    // $count members whose weights add up to exactly $totalWeight
    private function bulkRoster(int $count, int $totalWeight): void
    {
        $each = intdiv($totalWeight, $count);
        $extra = $totalWeight - $each * $count;
        for ($i = 0; $i < $count; $i++) {
            CouncilRosterMember::create(['member_id' => '9990000' . str_pad((string) $i, 4, '0', STR_PAD_LEFT), 'weight' => $each + ($i < $extra ? 1 : 0)]);
        }
    }

    public function test_adding_to_the_roster_later_does_not_change_an_existing_meeting_until_refreshed()
    {
        $this->rosterOf3();
        $m = $this->newMeeting();

        CouncilRosterMember::create(['member_id' => '99999999990', 'weight' => 1]);
        $this->assertSame(10, $m->fresh()->stats()['total_weight']);

        $m->freezeFromRoster();
        $m->save();
        $this->assertSame(11, $m->fresh()->total_weight);
    }

    public function test_attendance_rules()
    {
        [$a, $b, , ] = array_pad($this->rosterOf3(), 4, null);
        [, , , $outsider] = array_pad($this->sampleMemberIds(4), 4, null);
        $m = $this->newMeeting();
        $ctrl = app(CouncilMeetingsController::class);
        $add = fn($memberId) => $ctrl->storeAttendance($this->ajax(['member_id' => $memberId]), $m->id);

        $this->assertSame(200, $add($a)->getStatusCode(), 'roster member is marked present');
        $this->assertEquals(4, $m->attendance()->where('member_id', $a)->value('weight'));

        $this->assertSame(422, $add($a)->getStatusCode(), 'the same member twice is refused');
        $this->assertSame(1, $m->attendance()->count());

        $this->assertSame(422, $add($outsider)->getStatusCode(), 'a non-roster member is refused');
        $this->assertSame(1, $m->attendance()->count());

        $add($b);
        $stats = $m->fresh()->stats();
        $this->assertSame(9, $stats['present_weight']);
        $this->assertTrue($stats['majority_reached']);
        $this->assertTrue($stats['two_thirds_reached']);
    }

    public function test_the_database_unique_key_blocks_a_raced_attendance_duplicate()
    {
        [$a] = $this->rosterOf3();
        $m = $this->newMeeting();
        CouncilMeetingAttendance::create(['meeting_id' => $m->id, 'member_id' => $a, 'weight' => 4, 'checked_in_at' => now()]);

        $this->expectException(QueryException::class);
        CouncilMeetingAttendance::create(['meeting_id' => $m->id, 'member_id' => $a, 'weight' => 4, 'checked_in_at' => now()]);
    }

    public function test_a_closed_meeting_refuses_attendance()
    {
        [$a] = $this->rosterOf3();
        $m = $this->newMeeting();
        $m->update(['is_active' => false]);

        $r = app(CouncilMeetingsController::class)->storeAttendance($this->ajax(['member_id' => $a]), $m->id);

        $this->assertSame(422, $r->getStatusCode());
    }

    // ---- special members ----

    private function bigNoisyJpeg(): UploadedFile
    {
        $im = imagecreatetruecolor(1800, 1800);
        for ($i = 0; $i < 4000; $i++) {
            imagefilledrectangle($im, rand(0, 1700), rand(0, 1700), rand(0, 1800), rand(0, 1800), imagecolorallocate($im, rand(0, 255), rand(0, 255), rand(0, 255)));
        }
        $tmp = sys_get_temp_dir() . '/council_test_photo_' . uniqid() . '.jpg';
        imagejpeg($im, $tmp, 95);

        return new UploadedFile($tmp, 'photo.jpg', 'image/jpeg', null, true);
    }

    private function createSpecial(array $data, ?UploadedFile $photo = null): void
    {
        $req = Request::create('/x', 'POST', $data, [], $photo ? ['photo' => $photo] : []);
        $req->setLaravelSession(app('session.store'));
        app(SpecialMembersController::class)->store($req);
    }

    public function test_a_special_member_gets_a_reserved_id_a_login_row_and_a_small_photo()
    {
        $photo = $this->bigNoisyJpeg();
        $original = filesize($photo->getRealPath());
        $this->assertGreaterThan(60000, $original);

        $this->createSpecial(['full_name' => 'ايلي فريد المر', 'phone' => '+1 5122990023', 'sector' => 'مدن الولايات المتحدة الأميركية'], $photo);

        $sm = SpecialMember::where('full_name', 'ايلي فريد المر')->firstOrFail();
        $this->assertSame(13, strlen((string) $sm->member_id));
        $this->assertStringStartsWith('99', (string) $sm->member_id);

        $fpm = DB::table('fpm_users')->where('MemberId', $sm->member_id)->first();
        $this->assertNotNull($fpm);
        $this->assertSame('15122990023', $fpm->MobileNumber);
        $this->assertSame(1, (int) $fpm->member_status);
        $this->assertGreaterThan(0, strlen($fpm->PersonImage));
        $this->assertLessThanOrEqual(60000, strlen($fpm->PersonImage));

        // the lookup the app's login does
        $this->assertNotNull(\App\V2\FpmUser::where([['MemberId', $sm->member_id], ['MobileNumber', '15122990023']])->first());
    }

    public function test_special_member_ids_are_sequential_and_a_duplicate_phone_is_refused()
    {
        $this->createSpecial(['full_name' => 'T First', 'phone' => '+44 7000000001']);
        $this->createSpecial(['full_name' => 'T Second', 'phone' => '+44 7000000002']);
        $this->createSpecial(['full_name' => 'T Dup phone', 'phone' => '447000000001']);

        $first = SpecialMember::where('full_name', 'T First')->firstOrFail();
        $second = SpecialMember::where('full_name', 'T Second')->firstOrFail();

        $this->assertSame((int) $first->member_id + 1, (int) $second->member_id);
        $this->assertFalse(SpecialMember::where('full_name', 'T Dup phone')->exists());
    }

    public function test_a_removed_fpm_row_can_be_restored_and_the_member_added_to_the_roster()
    {
        $this->createSpecial(['full_name' => 'T Restore', 'phone' => '+44 7000000009']);
        $sm = SpecialMember::where('full_name', 'T Restore')->firstOrFail();

        DB::table('fpm_users')->where('MemberId', $sm->member_id)->delete();
        app(SpecialMembersController::class)->restore($sm->id);
        $this->assertTrue(DB::table('fpm_users')->where('MemberId', $sm->member_id)->exists());

        $r = app(CouncilRosterController::class)->store($this->ajax(['member_id' => $sm->member_id, 'weight' => 3, 'role' => 'x']));
        $this->assertSame(200, $r->getStatusCode());
        $this->assertEquals(3, CouncilRosterMember::where('member_id', (string) $sm->member_id)->value('weight'));
    }

    // ---- the admin pages render ----

    public function test_the_council_admin_pages_render()
    {
        $this->rosterOf3();
        $m = $this->newMeeting();
        $admin = $this->adminUser();

        foreach ([
            route('admin.council-roster.index', [], false),
            route('admin.council-meetings.index', [], false),
            route('admin.council-meetings.create', [], false),
            route('admin.council-meetings.show', $m->id, false),
            route('admin.special-members.index', [], false),
        ] as $url) {
            $resp = $this->actingAs($admin, 'admin')->get($url);
            $this->assertSame(200, $resp->getStatusCode(), $url . ' -> ' . $resp->getStatusCode());
        }
    }

    // ---- live display board ----

    public function test_the_live_board_page_and_its_data_feed_follow_the_attendance()
    {
        [$a, $b, $c] = $this->rosterOf3();
        $m = $this->newMeeting('T board');
        $admin = $this->adminUser();

        $html = $this->actingAs($admin, 'admin')->get(route('admin.council-meetings.board', $m->id, false))->assertOk()->getContent();
        $this->assertStringContainsString('window.BOARD', $html);
        $this->assertStringContainsString('window.BOARD_URL', $html);

        $feed = route('admin.council-meetings.board-data', $m->id, false);
        $d = $this->actingAs($admin, 'admin')->getJson($feed)->assertOk()->json();
        $this->assertSame(0, $d['stats']['present_count']);
        $this->assertSame(3, $d['stats']['absent_count']);
        $this->assertCount(0, $d['recent']);

        app(CouncilMeetingsController::class)->storeAttendance($this->ajax(['member_id' => $b]), $m->id);

        $d = $this->actingAs($admin, 'admin')->getJson($feed)->assertOk()->json();
        $this->assertSame(1, $d['stats']['present_count']);
        $this->assertSame(5, $d['stats']['present_weight']);
        $this->assertSame(2, $d['stats']['absent_count']);
        $this->assertCount(3, $d['members']);
        $this->assertCount(1, $d['recent']);
        $this->assertSame((string) $b, (string) $d['recent'][0]['id']);
        $this->assertNotEmpty($d['groups']);
    }

    public function test_the_board_serves_council_members_photos_only()
    {
        [$a, $b, $c] = $this->rosterOf3();
        $m = $this->newMeeting('T board photo');
        $admin = $this->adminUser();
        $jpeg = "\xFF\xD8\xFF\xE0" . str_repeat('x', 200);

        DB::table('fpm_users')->where('MemberId', $a)->update(['PersonImage' => $jpeg]);
        DB::table('fpm_users')->where('MemberId', $b)->update(['PersonImage' => base64_encode($jpeg)]);
        DB::table('fpm_users')->where('MemberId', $c)->update(['PersonImage' => '']);
        $outsider = $this->sampleMemberIds(4)[3];
        DB::table('fpm_users')->where('MemberId', $outsider)->update(['PersonImage' => $jpeg]);

        $url = fn($id) => route('admin.council-meetings.photo', [$m->id, $id], false);

        $r = $this->actingAs($admin, 'admin')->get($url($a))->assertOk();
        $this->assertSame('image/jpeg', $r->headers->get('Content-Type'));
        $this->assertSame($jpeg, $r->getContent(), 'raw bytes are served as they are');
        $this->assertSame($jpeg, $this->get($url($b))->assertOk()->getContent(), 'base64 text is decoded');
        $this->get($url($outsider))->assertNotFound();

        $d = $this->getJson(route('admin.council-meetings.board-data', $m->id, false))->json();
        $byId = collect($d['members'])->keyBy('id');
        $this->assertSame($url($a), $byId[(string) $a]['photo']);
        $this->assertNull($byId[(string) $c]['photo'] ?? null, 'no photo on file means no photo url (the board falls back to the letter)');
    }

    public function test_the_roster_and_meeting_pages_show_member_photos()
    {
        [$a, $b, $c] = $this->rosterOf3();
        $m = $this->newMeeting('T photos');
        $admin = $this->adminUser();
        $jpeg = "\xFF\xD8\xFF\xE0" . str_repeat('x', 200);
        DB::table('fpm_users')->where('MemberId', $a)->update(['PersonImage' => $jpeg]);
        $outsider = $this->sampleMemberIds(4)[3];
        DB::table('fpm_users')->where('MemberId', $outsider)->update(['PersonImage' => $jpeg]);

        // the roster page's own photo route: council members only
        $this->actingAs($admin, 'admin')->get(route('admin.council-roster.photo', $a, false))->assertOk();
        $this->get(route('admin.council-roster.photo', $outsider, false))->assertNotFound();

        // both pages carry a photo for every row (present and absent tables on the meeting page)
        app(CouncilMeetingsController::class)->storeAttendance($this->ajax(['member_id' => $a]), $m->id);
        $roster = $this->get(route('admin.council-roster.index', [], false))->assertOk()->getContent();
        $this->assertSame(3, substr_count($roster, 'class="mphoto"'));
        $show = $this->get(route('admin.council-meetings.show', $m->id, false))->assertOk()->getContent();
        $this->assertSame(3, substr_count($show, 'class="mphoto"'), '1 present + 2 absent');
        $this->assertStringContainsString('/photo/' . $a, $show);
    }

    public function test_a_representatives_upload_beats_the_member_list_photo_when_it_exists()
    {
        [$a, $b] = $this->rosterOf3();
        $jpeg = "\xFF\xD8\xFF\xE0" . str_repeat('x', 200);
        DB::table('fpm_users')->where('MemberId', $a)->update(['PersonImage' => $jpeg]);
        DB::table('fpm_users')->where('MemberId', $b)->update(['PersonImage' => $jpeg]);

        $fileA = 'test-rep-' . uniqid() . '.jpg';
        $fileB = 'test-rep-' . uniqid() . '.jpg';
        $cat = DB::table('dynamic_representatives')->insertGetId(['title' => '{}', 'text' => '{}', 'order' => 999, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('persons')->insert([
            ['name' => 'T rep A', 'member_id' => $a, 'image' => $fileA, 'dynamic_representative_id' => $cat, 'order' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'T rep B', 'member_id' => $b, 'image' => $fileB, 'dynamic_representative_id' => $cat, 'order' => 0, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // S3 has A's file but not B's
        \Illuminate\Support\Facades\Http::fake(fn($req) => \Illuminate\Support\Facades\Http::response('', str_contains($req->url(), $fileA) ? 200 : 404));

        $this->actingAs($this->adminUser(), 'admin');
        $ra = $this->get(route('admin.council-roster.photo', $a, false));
        $ra->assertRedirect();
        $this->assertStringContainsString($fileA, $ra->headers->get('Location'), 'the Representatives upload wins');

        $rb = $this->get(route('admin.council-roster.photo', $b, false));
        $rb->assertOk();
        $this->assertSame($jpeg, $rb->getContent(), 'a missing S3 file falls back to the member-list photo');

        $this->assertContains((string) $a, \App\Support\CouncilMemberLookup::idsWithPhoto([(string) $a]));
    }

    public function test_an_unlinked_representatives_record_is_matched_to_the_member_by_exact_name()
    {
        [, , $c] = $this->rosterOf3();
        $fullName = DB::table('fpm_users')->where('MemberId', $c)->value('UserFullName');
        $file = 'test-rep-byname-' . uniqid() . '.jpg';
        $cat = DB::table('dynamic_representatives')->insertGetId(['title' => '{}', 'text' => '{}', 'order' => 999, 'created_at' => now(), 'updated_at' => now()]);
        // no member_id on the record, the name is stored as {"ar":..,"en":..} with an alef variant
        DB::table('persons')->insert(['name' => json_encode(['ar' => str_replace('ا', 'أ', $fullName), 'en' => $fullName], JSON_UNESCAPED_UNICODE),
            'member_id' => null, 'image' => $file, 'dynamic_representative_id' => $cat, 'order' => 0, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('persons')->insert(['name' => json_encode(['ar' => 'اسم مختلف تماما'], JSON_UNESCAPED_UNICODE),
            'member_id' => null, 'image' => 'someone-else.jpg', 'dynamic_representative_id' => $cat, 'order' => 0, 'created_at' => now(), 'updated_at' => now()]);

        $files = \App\Support\CouncilMemberLookup::repImageFiles([(string) $c]);
        $this->assertSame($file, $files[(string) $c] ?? null, 'matched by name even though the alef is written differently');
        $this->assertNotSame('someone-else.jpg', $files[(string) $c] ?? null);

        // a space inside the name does not matter ("عطا الله" vs "عطالله")
        $spaced = DB::table('fpm_users')->where('MemberId', $c)->update(['UserFullName' => 'جورج نعيم عطا الله']);
        DB::table('persons')->where('image', $file)->update(['name' => json_encode(['ar' => 'جورج نعيم عطاالله'], JSON_UNESCAPED_UNICODE)]);
        $this->assertSame($file, \App\Support\CouncilMemberLookup::repImageFiles([(string) $c])[(string) $c] ?? null);
    }

    public function test_logged_in_means_active_since_tracking_began_not_just_having_an_old_account()
    {
        [$a, $b, $c] = $this->rosterOf3();
        // $a: old account, never active.  $b: old account + recorded activity.  $c: no account at all.
        $this->makeAppUser('T old account', $a, ['created_at' => '2021-03-01 10:00:00']);
        $userB = $this->makeAppUser('T active', $b, ['created_at' => '2021-03-01 10:00:00']);
        DB::table('user_activity_logs')->insertOrIgnore(['app_user_id' => $userB, 'activity_date' => '2026-11-10', 'created_at' => now()]);

        $res = \App\Support\CouncilMemberLookup::activitySince([$a, $b, $c]);
        $this->assertArrayHasKey((string) $b, $res['last_seen']);
        $this->assertArrayNotHasKey((string) $a, $res['last_seen'], 'an old account alone is not "logged in"');
        $this->assertArrayNotHasKey((string) $c, $res['last_seen']);
        $this->assertNotNull($res['since']);

        $m = $this->newMeeting('T readiness');
        $html = $this->actingAs($this->adminUser(), 'admin')->get(route('admin.council-meetings.show', $m->id, false))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#<strong>1</strong> of <strong>3</strong> members have logged in#', $html);
        $page = $this->get(route('admin.council-roster.index', [], false))->assertOk()->getContent();
        $this->assertStringContainsString('last seen 10 Nov 2026', $page);
        $this->assertStringContainsString('old account only', $page);
    }

    public function test_the_live_board_is_not_open_to_the_public()
    {
        $this->rosterOf3();
        $m = $this->newMeeting('T board guest');

        auth('admin')->forgetUser(); // setUp() had logged an admin in
        $status = $this->get(route('admin.council-meetings.board-data', $m->id, false))->getStatusCode();
        $this->assertContains($status, [301, 302, 401, 403], 'a visitor who is not logged in must not get the feed');
    }

    public function test_the_roster_page_has_role_sector_district_filters_built_from_the_data()
    {
        [$a, $b, $c] = $this->sampleMemberIds(3);
        CouncilRosterMember::whereNotNull('id')->delete();
        CouncilRosterMember::create(['member_id' => $a, 'weight' => 1, 'role' => 'نائب', 'sector' => 'مجلس وطني', 'district' => 'فئة 1']);
        CouncilRosterMember::create(['member_id' => $b, 'weight' => 2, 'role' => 'عضو', 'sector' => 'مجلس قضاء', 'district' => 'راشيا']);
        CouncilRosterMember::create(['member_id' => $c, 'weight' => 3, 'role' => 'عضو', 'sector' => 'مجلس قضاء', 'district' => 'زحلة']);

        $html = $this->actingAs($this->adminUser(), 'admin')->get(route('admin.council-roster.index', [], false))->assertOk()->getContent();

        // the three dropdowns + the text search exist
        foreach (['id="filterRole"', 'id="filterSector"', 'id="filterDistrict"', 'id="filterText"', 'id="filterClear"'] as $needle) {
            $this->assertStringContainsString($needle, $html);
        }

        // options come from the roster itself, each value once ("عضو" is used by two members)
        $this->assertSame(1, substr_count($html, '<option value="عضو">'));
        $this->assertSame(1, substr_count($html, '<option value="نائب">'));
        $this->assertStringContainsString('<option value="راشيا">', $html);
        $this->assertStringContainsString('<option value="زحلة">', $html);
        $this->assertStringContainsString('<option value="فئة 1">', $html);

        // every row carries what the filters compare against
        $this->assertSame(3, substr_count($html, 'data-role="'));
        $this->assertStringContainsString('data-district="راشيا"', $html);
        $this->assertStringContainsString('data-sector="مجلس قضاء"', $html);
        $this->assertSame(3, substr_count($html, 'data-search="'));
    }
}
