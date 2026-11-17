<?php

namespace Tests\Feature\NationalCouncil;

use App\Http\Controllers\Api\V2\ApiController;
use App\Http\Middleware\RequireAnyFullPermission;
use App\V2\AppUser;
use App\V2\CouncilMeeting;
use App\V2\CouncilRosterMember;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CouncilCheckinApiAndSecurityTest extends CouncilTestCase
{
    // ---- QR scan endpoints ----

    private function callApi(int $userId, string $method, array $data): array
    {
        $r = Request::create('/x', 'POST', $data);
        $r->merge(['user' => AppUser::find($userId)]);
        $res = app(ApiController::class)->$method($r);

        return [$res->getStatusCode(), json_decode($res->getContent(), true)];
    }

    private function meetingWithRosterMember(): array
    {
        [$id] = $this->sampleMemberIds(1);
        CouncilRosterMember::create(['member_id' => $id, 'weight' => 6, 'role' => 'Role X']);
        $m = new CouncilMeeting(['title' => 'T api', 'is_active' => true]);
        $m->freezeFromRoster();
        $m->save();

        return [$m, $id];
    }

    public function test_the_checkin_list_includes_open_council_meetings_and_keeps_the_events_key()
    {
        [$m] = $this->meetingWithRosterMember();
        $scanner = $this->makeAppUser('Scanner', '99900000099', ['can_scan_checkin' => 1]);

        [, $body] = $this->callApi($scanner, 'getCheckinEvents', []);

        $this->assertArrayHasKey('data', $body);
        $this->assertTrue(collect($body['council_meetings'])->contains('id', $m->id));
    }

    public function test_scanning_a_roster_member_checks_them_in_and_scanning_again_says_already()
    {
        [$m, $id] = $this->meetingWithRosterMember();
        $scanner = $this->makeAppUser('Scanner', '99900000099', ['can_scan_checkin' => 1]);

        [$code, $body] = $this->callApi($scanner, 'councilMeetingCheckIn', ['meeting_id' => $m->id, 'member_id' => $id]);
        $this->assertSame(200, $code);
        $this->assertFalse($body['already_checked_in']);
        $this->assertSame(6, $body['weight']);
        $this->assertSame('Role X', $body['position']);
        $this->assertSame(6, $body['present_weight']);

        [, $again] = $this->callApi($scanner, 'councilMeetingCheckIn', ['meeting_id' => $m->id, 'member_id' => $id]);
        $this->assertTrue($again['already_checked_in']);
        $this->assertSame(1, $m->attendance()->count());
    }

    public function test_a_non_council_member_a_non_scanner_and_a_closed_meeting_are_refused()
    {
        [$m, $id] = $this->meetingWithRosterMember();
        [, $outsider] = $this->sampleMemberIds(2);
        $scanner = $this->makeAppUser('Scanner', '99900000099', ['can_scan_checkin' => 1]);
        $plain = $this->makeAppUser('Plain', '99900000098');

        [$code] = $this->callApi($scanner, 'councilMeetingCheckIn', ['meeting_id' => $m->id, 'member_id' => $outsider]);
        $this->assertSame(400, $code);

        [$code] = $this->callApi($plain, 'councilMeetingCheckIn', ['meeting_id' => $m->id, 'member_id' => $id]);
        $this->assertSame(400, $code);

        $m->update(['is_active' => false]);
        [$code] = $this->callApi($scanner, 'councilMeetingCheckIn', ['meeting_id' => $m->id, 'member_id' => $id]);
        $this->assertSame(400, $code);
        $this->assertSame(0, $m->attendance()->count());
    }

    // ---- permission gaps ----

    private function adminWithProfile(string $level, array $pageIds): \App\User
    {
        $pid = DB::table('profiles')->insertGetId(['name' => 'T ' . uniqid(), 'created_at' => now(), 'updated_at' => now()]);
        foreach ($pageIds as $pg) {
            DB::table('profile_permissions')->insert(['profile_id' => $pid, 'page_id' => $pg, 'level' => $level, 'created_at' => now(), 'updated_at' => now()]);
        }
        $u = $this->adminUser()->replicate();
        $u->email = uniqid() . '@test.test';
        $u->profile_id = $pid;
        $u->save();

        return $u;
    }

    private function httpStatus(\App\User $admin, string $routeName): int
    {
        return $this->actingAs($admin, 'admin')->get(route($routeName, [], false))->getStatusCode();
    }

    public function test_user_polls_and_volunteer_users_follow_the_polls_and_volunteers_permissions()
    {
        $polls = DB::table('pages')->where('url', 'admin.polls.index')->value('id');
        $vols = DB::table('pages')->where('url', 'admin.volunteers.index')->value('id');

        $none = $this->adminWithProfile('view', []);
        $view = $this->adminWithProfile('view', [$polls, $vols]);

        $this->assertSame(403, $this->httpStatus($none, 'admin.userPolls.index'));
        $this->assertSame(200, $this->httpStatus($view, 'admin.userPolls.index'));
        $this->assertSame(403, $this->httpStatus($view, 'admin.userPolls.create'), 'create needs Full');
        $this->assertSame(403, $this->httpStatus($none, 'admin.volunteerUsers.index'));
        $this->assertSame(200, $this->httpStatus($view, 'admin.volunteerUsers.index'));
    }

    public function test_upload_and_delete_helpers_need_full_control_of_some_page()
    {
        $polls = DB::table('pages')->where('url', 'admin.polls.index')->value('id');
        $viewOnly = $this->adminWithProfile('view', [$polls]);
        $full = $this->adminWithProfile('full', [$polls]);
        $mw = new RequireAnyFullPermission();

        $run = function ($user) use ($mw) {
            auth('admin')->setUser($user);
            try {
                return $mw->handle(Request::create('/x', 'POST'), fn($q) => response('OK'))->getContent();
            } catch (HttpException $e) {
                return $e->getStatusCode();
            }
        };

        $this->assertSame(403, $run($viewOnly));
        $this->assertSame('OK', $run($full));
    }

    // ---- member photo + tokens ----

    public function test_member_photo_is_served_only_for_representatives_and_candidates()
    {
        $withPhoto = DB::table('fpm_users')->whereRaw('LENGTH(PersonImage) > 100')->limit(60)->pluck('MemberId')->all();
        $this->assertGreaterThanOrEqual(2, count($withPhoto));

        $publicIds = DB::table('persons')->whereNotNull('member_id')->pluck('member_id')->map(fn($i) => (string) $i)->all();
        $candidateIds = DB::table('internal_election_candidates')->whereNotNull('member_id')->pluck('member_id')->map(fn($i) => (string) $i)->all();
        $plain = collect($withPhoto)->first(fn($id) => !in_array((string) $id, array_merge($publicIds, $candidateIds), true));
        $this->assertNotNull($plain, 'needs an ordinary member with a photo');

        DB::table('persons')->insert([
            'dynamic_representative_id' => DB::table('dynamic_representatives')->value('id') ?: 1,
            'name' => 'T rep', 'order' => 1, 'rep_order' => 1, 'type' => 'x',
            'member_id' => (string) $plain, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $status = function ($id) {
            try {
                return app(ApiController::class)->getMemberPhoto($id)->getStatusCode();
            } catch (HttpException $e) {
                return $e->getStatusCode();
            }
        };

        $this->assertSame(200, $status($plain), 'now a representative: served');

        DB::table('persons')->where('name', 'T rep')->delete();
        $this->assertSame(404, $status($plain), 'ordinary member: refused');
    }

    public function test_tokens_and_pins_come_from_a_secure_generator()
    {
        $t = new class {
            use \App\Http\Traits\V2\TokenTrait;
        };

        $a = $t->generate_token();
        $this->assertSame(128, strlen($a));
        $this->assertTrue(ctype_xdigit($a));
        $this->assertNotSame($a, $t->generate_token());
        $this->assertMatchesRegularExpression('/^\d{4}$/', $t->generatePIN(4));
    }
}
