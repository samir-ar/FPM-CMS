<?php

namespace Tests\Feature;

use App\AppUser;
use App\Http\Repositories\V2\RegistrationRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Feature\NationalCouncil\CouncilTestCase;

// The SMS code is the only proof that a person holds the phone, so: it is never returned by the API, the real login
// token does not change until the code is right, a token handed out before that works for nothing, a code expires,
// and guessing is limited.
class LoginOtpTest extends CouncilTestCase
{
    private $member;
    private $phone;
    private $sent = [];

    protected function setUp(): void
    {
        parent::setUp();

        $fpm = DB::table('fpm_users')->whereNotNull('MobileNumber')->where('MobileNumber', '!=', '')->where('member_status', 1)->orderBy('id')->first();
        if (!$fpm) {
            $this->markTestSkipped('Needs an active fpm_users row with a mobile number.');
        }
        $this->member = (string) $fpm->MemberId;
        $this->phone = (string) $fpm->MobileNumber;

        foreach (['otp-send:', 'otp-guess:'] as $p) {
            RateLimiter::clear($p . ltrim($this->member, '0'));
        }
        RateLimiter::clear('otp-ip:127.0.0.1');

        // never send a real SMS from a test: capture the text instead
        $sent = &$this->sent;
        $this->partialMock(RegistrationRepository::class, function ($m) use (&$sent) {
            $m->shouldAllowMockingProtectedMethods();
            $m->shouldReceive('deliverSms')->andReturnUsing(function ($data) use (&$sent) { $sent[] = $data; return null; });
        });
    }

    protected function tearDown(): void
    {
        // leave no rate-limit counters behind for the real login of this member on the local site
        foreach (['otp-send:', 'otp-guess:'] as $p) {
            RateLimiter::clear($p . ltrim((string) $this->member, '0'));
        }
        RateLimiter::clear('otp-ip:127.0.0.1');

        parent::tearDown();
    }

    private function requestCode()
    {
        return $this->postJson('/api/v2/send-verification', [
            'member_id' => $this->member, 'phone_number' => $this->phone, 'platform' => 'android', 'build_number' => 999999999,
        ]);
    }

    private function verify(string $token, string $pin)
    {
        return $this->postJson('/api/v2/verify-pin', ['token' => $token, 'pin' => $pin]);
    }

    private function currentPin(): string
    {
        return (string) end($this->sent)['message'] === '' ? '' : trim(str_replace('Cde', '', end($this->sent)['message']));
    }

    public function test_the_reply_never_contains_the_code_and_the_pending_token_opens_nothing(): void
    {
        $res = $this->requestCode()->assertOk();
        $this->assertArrayNotHasKey('pin', $res->json());
        $token = $res->json('token');
        $this->assertNotEmpty($token);
        $this->assertNotEmpty($this->currentPin(), 'the code went out by SMS');

        $this->assertNull(AppUser::where('token', $token)->first(), 'the token handed out before the code is entered is not a login token');
        $this->assertNotNull(AppUser::where('pending_token', $token)->first());
    }

    public function test_asking_for_a_code_does_not_log_the_real_member_out(): void
    {
        $this->requestCode()->assertOk();
        $user = AppUser::where('member_id', $this->member)->orderBy('id')->first();
        $user->token = 'REAL-SESSION-TOKEN';
        $user->verified = true;
        $user->save();

        $this->requestCode()->assertOk();
        $this->assertSame('REAL-SESSION-TOKEN', AppUser::find($user->id)->token, 'the member stays logged in');
    }

    public function test_the_right_code_logs_in_and_works_only_once(): void
    {
        $token = $this->requestCode()->json('token');
        $pin = $this->currentPin();

        $this->verify($token, $pin)->assertOk();
        $user = AppUser::where('token', $token)->first();
        $this->assertNotNull($user, 'the pending token is now the real login token');
        $this->assertNull($user->pending_token);
        $this->assertEquals(1, $user->verified);

        $this->verify($token, $pin)->assertStatus(400);   // the same token + code cannot be replayed
    }

    public function test_a_wrong_code_is_refused_and_guessing_is_limited(): void
    {
        $token = $this->requestCode()->json('token');
        $pin = $this->currentPin();
        $wrong = $pin === '0000' ? '0001' : '0000';

        for ($i = 0; $i < 10; $i++) {
            $this->verify($token, $wrong)->assertStatus(400);
        }
        // locked now, even for the right code
        $this->verify($token, $pin)->assertStatus(429);
        $this->assertNull(AppUser::where('token', $token)->first());
    }

    public function test_an_expired_code_is_refused(): void
    {
        $token = $this->requestCode()->json('token');
        $pin = $this->currentPin();
        AppUser::where('pending_token', $token)->update(['verification_expires_at' => now()->subMinute()]);

        $this->verify($token, $pin)->assertStatus(400);
        $this->assertNull(AppUser::where('token', $token)->first());
    }

    public function test_too_many_code_requests_for_one_member_are_refused(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->requestCode()->assertOk();
        }
        $this->requestCode()->assertStatus(429);
    }

    public function test_an_empty_pin_can_never_delete_an_account(): void
    {
        $user = AppUser::where('member_id', $this->member)->first() ?? new AppUser();
        $ctrl = app(\App\Http\Controllers\Api\V2\ApiController::class);
        $user->verification_nb = null;
        $req = \Illuminate\Http\Request::create('/x', 'POST', []);
        $req->merge(['user' => $user, 'loggedInUser' => null]);
        app()->instance('request', $req);
        $res = $ctrl->deleteAccount();
        $this->assertSame(400, $res->getStatusCode());
    }
}
