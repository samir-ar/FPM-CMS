<?php

namespace Tests\Feature\NationalCouncil;

use App\Http\Controllers\Api\V2\TalentController;
use App\V2\AppUser;
use App\V2\TalentRole;
use App\V2\TalentSubmission;
use App\V2\TalentTrack;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;

// FPM Talent API: what each audience sees, open / upcoming / closed, member and guest submissions, limits, private files.
class TalentApiTest extends CouncilTestCase
{
    private string $body = '';
    private int $status = 200;

    private function callTalent(string $method, array $payload = [], ?int $userId = null, array $files = []): array
    {
        $req = Request::create('/api/v2/talent-' . $method, 'POST', $payload, [], $files, ['REMOTE_ADDR' => '10.9.9.9']);
        if ($userId) {
            $req->merge(['user' => AppUser::withTrashed()->find($userId)]);
        }
        app()->instance('request', $req);
        $name = ['config' => 'config', 'faqs' => 'faqs', 'send-code' => 'guestSendCode', 'verify' => 'guestVerify', 'submit' => 'submit'][$method];
        $res = app(TalentController::class)->$name($req);
        $this->status = $res->getStatusCode();
        $this->body = $res->getContent();

        return json_decode($this->body, true) ?? [];
    }

    private function open(string $code, array $attrs = []): TalentTrack
    {
        $t = TalentTrack::where('code', $code)->firstOrFail();
        $t->update($attrs + ['is_enabled' => true, 'opens_at' => now()->subHour(), 'closes_at' => now()->addDay()]);

        return $t->fresh();
    }

    private function roleIds(int $n = 1): array
    {
        return TalentRole::where('is_active', true)->orderBy('sort_order')->limit($n)->pluck('id')->map(fn ($i) => ['id' => $i])->all();
    }

    private function peerPayload(array $over = []): array
    {
        return $over + [
            'track' => 'peer', 'nominee_name' => 'ZQ شخص', 'nominee_district' => 'المتن', 'nominee_town' => 'ضبية', 'profession' => 'مهندس', 'relation_to_fpm' => 'مؤيد/ة للتيار',
            'reasons' => 'سبب', 'strengths' => 'قوة', 'relationship_between' => 'علاقة صداقة', 'roles' => $this->roleIds(2), 'acknowledged' => '1',
        ];
    }

    private function selfPayload(array $over = []): array
    {
        return $over + [
            'track' => 'self', 'education_level' => 'ماجيستير', 'district' => 'المتن', 'town' => 'ضبية', 'academic' => 'ماجستير', 'professional' => 'خبرة', 'interests' => 'سياسات', 'cover_letter' => 'رسالة',
            'roles' => $this->roleIds(1), 'acknowledged' => '1',
        ];
    }

    private function cv(): array
    {
        return ['cv' => UploadedFile::fake()->create('cv.pdf', 120, 'application/pdf')];
    }

    private function ok(): bool
    {
        return (bool) (json_decode($this->body, true)['success'] ?? false);
    }

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Cache::flush();
    }

    public function test_nothing_is_open_by_default_and_a_guest_sees_no_track(): void
    {
        $u = $this->makeAppUser('T talent', '5900000001');

        $member = $this->callTalent('config', [], $u);
        $this->assertTrue($member['is_member']);
        $this->assertSame(['peer', 'self'], collect($member['tracks'])->pluck('code')->all());
        $this->assertSame(['closed', 'closed'], collect($member['tracks'])->pluck('state')->all());
        $this->assertCount(9, $member['roles']);

        $guest = $this->callTalent('config');
        $this->assertFalse($guest['is_member']);
        $this->assertSame([], $guest['tracks'], 'members-only tracks stay hidden from guests');
    }

    public function test_open_upcoming_closed_and_the_audience(): void
    {
        $u = $this->makeAppUser('T talent 2', '5900000002');
        $this->open('peer');
        $this->open('self', ['audience' => 'public']);

        $this->assertSame(['open', 'open'], collect($this->callTalent('config', [], $u)['tracks'])->pluck('state')->all());
        $guest = $this->callTalent('config');
        $this->assertSame(['self'], collect($guest['tracks'])->pluck('code')->all(), 'a guest sees only the public self track');

        $this->open('self', ['audience' => 'public', 'opens_at' => now()->addDay(), 'closes_at' => now()->addDays(2)]);
        $self = collect($this->callTalent('config', [], $u)['tracks'])->firstWhere('code', 'self');
        $this->assertSame('upcoming', $self['state']);
        $this->assertStringContainsString('يفتح باب المشاركة', $self['message']);

        $this->open('self', ['opens_at' => now()->subDays(3), 'closes_at' => now()->subDay()]);
        $this->assertSame('closed', collect($this->callTalent('config', [], $u)['tracks'])->firstWhere('code', 'self')['state']);

        // the peer track can never be public, whatever the CMS says
        TalentTrack::where('code', 'peer')->update(['audience' => 'public']);
        $this->assertNotContains('peer', collect($this->callTalent('config')['tracks'])->pluck('code')->all());
    }

    public function test_a_member_peer_nomination_is_saved_with_his_details_and_limited_per_cycle(): void
    {
        $u = $this->makeAppUser('T nominator', '5900000003');
        $this->open('peer', ['max_per_member' => 2]);

        $this->callTalent('submit', $this->peerPayload(['nominee_name' => 'ZQ أ']), $u);
        $this->assertTrue($this->ok(), $this->body);
        $this->callTalent('submit', $this->peerPayload(['nominee_name' => 'ZQ ب']), $u);
        $this->assertTrue($this->ok(), $this->body);

        $this->callTalent('submit', $this->peerPayload(['nominee_name' => 'ZQ ج']), $u);
        $this->assertSame(400, $this->status);
        $this->assertStringContainsString('max_reached', $this->body);

        $row = TalentSubmission::where('nominee_name', 'ZQ أ')->first();
        $this->assertFalse($row->is_guest);
        $this->assertSame($u, (int) $row->user_id);
        $this->assertCount(2, $row->roles());

        // a new cycle gives the member a fresh allowance
        TalentTrack::where('code', 'peer')->update(['cycle' => 2]);
        $this->callTalent('submit', $this->peerPayload(['nominee_name' => 'ZQ د']), $u);
        $this->assertTrue($this->ok(), $this->body);
    }

    public function test_the_form_needs_every_field_the_ticks_and_a_real_role(): void
    {
        $u = $this->makeAppUser('T strict', '5900000004');
        $this->open('peer');

        foreach (['nominee_name', 'nominee_district', 'nominee_town', 'profession', 'relation_to_fpm', 'reasons', 'strengths', 'relationship_between', 'acknowledged', 'roles'] as $field) {
            $payload = $this->peerPayload();
            unset($payload[$field]);
            $this->callTalent('submit', $payload, $u);
            $this->assertSame(400, $this->status, "$field is required");
        }
        $this->callTalent('submit', $this->peerPayload(['roles' => [['id' => 999999]]]), $u);
        $this->assertSame(400, $this->status, 'an unknown role is refused');

        // "any other field" needs its text
        $other = TalentRole::where('allows_text', true)->value('id');
        $this->callTalent('submit', $this->peerPayload(['roles' => [['id' => $other]]]), $u);
        $this->assertSame(400, $this->status);
        $this->callTalent('submit', $this->peerPayload(['nominee_name' => 'ZQ آخر', 'roles' => [['id' => $other, 'other' => 'مجال ما']]]), $u);
        $this->assertTrue($this->ok(), $this->body);
        $this->assertSame('مجال ما', TalentSubmission::where('nominee_name', 'ZQ آخر')->first()->roles()[0]['other']);
    }

    public function test_a_guest_can_never_use_the_peer_track(): void
    {
        $this->open('peer');
        $this->callTalent('submit', $this->peerPayload(['guest_token' => 'x']));
        $this->assertSame(400, $this->status);
        $this->assertStringContainsString('not_allowed', $this->body);
    }

    public function test_the_self_track_needs_a_cv_and_keeps_files_on_the_private_disk(): void
    {
        $u = $this->makeAppUser('T self', '5900000005');
        $this->open('self');

        $this->callTalent('submit', $this->selfPayload(), $u);
        $this->assertSame(400, $this->status, 'the CV is required');

        $this->callTalent('submit', $this->selfPayload(), $u, $this->cv() + ['docs' => [UploadedFile::fake()->image('d.png'), UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')]]);
        $this->assertTrue($this->ok(), $this->body);

        $row = TalentSubmission::where('track', 'self')->where('user_id', $u)->first();
        $files = $row->attachments;
        $this->assertCount(3, $files);
        foreach ($files as $f) {
            Storage::disk('local')->assertExists($f->path);
            $this->assertStringStartsWith('talent/' . $row->id . '/', $f->path);
        }

        // a bad file type is refused
        $u2 = $this->makeAppUser('T self 2', '5900000006');
        $this->callTalent('submit', $this->selfPayload(), $u2, ['cv' => UploadedFile::fake()->create('evil.exe', 10, 'application/octet-stream')]);
        $this->assertSame(400, $this->status);
    }

    public function test_a_guest_confirms_his_phone_then_submits_once(): void
    {
        $this->open('self', ['audience' => 'public']);
        $phone = '96170123456';

        // wrong / expired / right code
        $this->callTalent('verify', ['phone' => $phone, 'code' => '123456']);
        $this->assertSame(400, $this->status);
        $this->assertStringContainsString('code_expired', $this->body);

        Cache::put('talent-guest-code:' . $phone, ['hash' => hash('sha256', '654321'), 'tries' => 0], 600);
        $this->callTalent('verify', ['phone' => $phone, 'code' => '111111']);
        $this->assertStringContainsString('invalid_code', $this->body);
        $ok = $this->callTalent('verify', ['phone' => $phone, 'code' => '654321']);
        $this->assertTrue($ok['success']);

        // no token / a made up token: refused
        $this->callTalent('submit', $this->selfPayload(['full_name' => 'ZQ ضيف', 'guest_token' => 'nope']), null, $this->cv());
        $this->assertStringContainsString('guest_not_verified', $this->body);

        $this->callTalent('submit', $this->selfPayload(['full_name' => 'ZQ ضيف', 'guest_token' => $ok['token']]), null, $this->cv());
        $this->assertTrue($this->ok(), $this->body);
        $row = TalentSubmission::where('submitter_name', 'ZQ ضيف')->first();
        $this->assertTrue($row->is_guest);
        $this->assertSame($phone, $row->submitter_phone);

        // the token is single use, and a second form from the same phone in the same cycle is refused
        $this->callTalent('submit', $this->selfPayload(['full_name' => 'ZQ ضيف 2', 'guest_token' => $ok['token']]), null, $this->cv());
        $this->assertStringContainsString('guest_not_verified', $this->body);
        Cache::put('talent-guest-token:second', $phone, 600);
        $this->callTalent('submit', $this->selfPayload(['full_name' => 'ZQ ضيف 3', 'guest_token' => 'second', 'cover_letter' => 'رسالة أخرى']), null, $this->cv());
        $this->assertStringContainsString('max_reached', $this->body);
    }

    public function test_five_wrong_codes_burn_the_code_and_codes_are_limited_per_phone(): void
    {
        $this->open('self', ['audience' => 'public']);
        $phone = '96171999888';
        Cache::put('talent-guest-code:' . $phone, ['hash' => hash('sha256', '222222'), 'tries' => 0], 600);
        for ($i = 0; $i < 5; $i++) {
            $this->callTalent('verify', ['phone' => $phone, 'code' => '000000']);
        }
        $this->callTalent('verify', ['phone' => $phone, 'code' => '222222']);
        $this->assertStringContainsString('code_expired', $this->body, 'the right code no longer works after 5 wrong tries');

        // a dummy number, and phpunit.xml has no SMS login, so nothing real is sent
        $this->assertEmpty(config('services.sms.username'), 'tests must not have an SMS login');
        for ($i = 0; $i < 3; $i++) {
            $this->callTalent('send-code', ['phone' => '96100000001', 'hardware_id' => 'T-hw-' . $i]);
            $this->assertTrue($this->ok(), $this->body);
        }
        $this->callTalent('send-code', ['phone' => '96100000001', 'hardware_id' => 'T-hw-x']);
        $this->assertSame(429, $this->status);
    }

    public function test_a_closed_track_refuses_and_a_double_tap_makes_one_row(): void
    {
        $u = $this->makeAppUser('T closed', '5900000007');
        $this->callTalent('submit', $this->peerPayload(), $u);
        $this->assertStringContainsString('track_closed', $this->body);

        $this->open('peer');
        $this->callTalent('submit', $this->peerPayload(['nominee_name' => 'ZQ مكرر']), $u);
        $this->callTalent('submit', $this->peerPayload(['nominee_name' => 'ZQ مكرر']), $u);
        $this->assertSame(1, TalentSubmission::where('nominee_name', 'ZQ مكرر')->count());
    }

    public function test_the_faq_comes_per_language_in_order_and_only_active_ones(): void
    {
        $now = now();
        DB::table('talent_faqs')->insert([
            ['language' => 'ar', 'question' => 'ZQ سؤال ٢', 'answer' => 'ج', 'is_active' => 1, 'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['language' => 'ar', 'question' => 'ZQ سؤال ١', 'answer' => 'ج', 'is_active' => 1, 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['language' => 'ar', 'question' => 'ZQ مخفي', 'answer' => 'ج', 'is_active' => 0, 'sort_order' => 3, 'created_at' => $now, 'updated_at' => $now],
            ['language' => 'en', 'question' => 'ZQ question', 'answer' => 'a', 'is_active' => 1, 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now],
        ]);

        $ar = collect($this->callTalent('faqs', ['language' => 'ar']))->pluck('question')->filter(fn ($q) => str_starts_with($q, 'ZQ'))->values()->all();
        $this->assertSame(['ZQ سؤال ١', 'ZQ سؤال ٢'], $ar);
        $en = collect($this->callTalent('faqs', ['language' => 'en']))->pluck('question')->filter(fn ($q) => str_starts_with($q, 'ZQ'))->values()->all();
        $this->assertSame(['ZQ question'], $en);
    }

    public function test_config_carries_the_cms_lists_and_only_their_active_values(): void
    {
        $u = $this->makeAppUser('T lists', '5900000010');
        $cfg = $this->callTalent('config', [], $u);

        $this->assertSame(['ملتزم/ة في التيار', 'مؤيد/ة للتيار', 'مستقل/ة'], $cfg['choices']['fpm_relation']);
        $this->assertSame(['علاقة قربة', 'علاقة صداقة', 'علاقة مهنية', 'علاقة حزبية'], $cfg['choices']['person_relation']);
        $this->assertCount(5, $cfg['choices']['education']);

        // the CMS adds one and switches one off
        DB::table('talent_choices')->insert(['group' => 'education', 'name' => 'ZQ شهادة جديدة', 'is_active' => 1, 'sort_order' => 99, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('talent_choices')->where('group', 'education')->where('name', 'ثانوي')->update(['is_active' => 0]);
        $education = $this->callTalent('config', [], $u)['choices']['education'];
        $this->assertContains('ZQ شهادة جديدة', $education);
        $this->assertNotContains('ثانوي', $education);
    }

    public function test_a_value_that_is_not_in_the_list_is_refused_and_a_new_cms_value_is_accepted(): void
    {
        $u = $this->makeAppUser('T lists 2', '5900000011');
        $this->open('peer');
        $this->open('self');

        $this->callTalent('submit', $this->peerPayload(['relation_to_fpm' => 'قيمة من خارج القائمة']), $u);
        $this->assertSame(400, $this->status);
        $this->callTalent('submit', $this->peerPayload(['relationship_between' => 'قيمة من خارج القائمة']), $u);
        $this->assertSame(400, $this->status);

        $this->callTalent('submit', $this->selfPayload(['education_level' => 'ZQ شهادة جديدة']), $u, $this->cv());
        $this->assertSame(400, $this->status, 'not in the list yet');
        DB::table('talent_choices')->insert(['group' => 'education', 'name' => 'ZQ شهادة جديدة', 'is_active' => 1, 'sort_order' => 99, 'created_at' => now(), 'updated_at' => now()]);
        $this->callTalent('submit', $this->selfPayload(['education_level' => 'ZQ شهادة جديدة']), $u, $this->cv());
        $this->assertTrue($this->ok(), $this->body);
        $this->assertSame('ZQ شهادة جديدة', TalentSubmission::where('user_id', $u)->where('track', 'self')->value('education_level'));
    }

    public function test_the_naming_form_keeps_the_nominee_district_and_town_and_a_guest_must_give_his_own(): void
    {
        $u = $this->makeAppUser('T district', '5900000012');
        $this->open('peer');
        $this->callTalent('submit', $this->peerPayload(['nominee_name' => 'ZQ بلدة']), $u);
        $row = TalentSubmission::where('nominee_name', 'ZQ بلدة')->first();
        $this->assertSame('المتن', $row->nominee_district);
        $this->assertSame('ضبية', $row->nominee_town);

        $this->open('self', ['audience' => 'public']);
        Cache::put('talent-guest-token:g1', '96170111222', 600);
        $payload = $this->selfPayload(['full_name' => 'ZQ ضيف بلا قضاء', 'guest_token' => 'g1']);
        unset($payload['district'], $payload['town']);
        $this->callTalent('submit', $payload, null, $this->cv());
        $this->assertSame(400, $this->status, 'a guest has no profile: district and town are required');
    }
}
