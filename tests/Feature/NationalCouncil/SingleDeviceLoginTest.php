<?php

namespace Tests\Feature\NationalCouncil;

use App\Http\Middleware\MyAuthV2;
use App\Http\Repositories\V2\RegistrationRepository;
use App\V2\AppUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// One account = ONE phone at a time. A second login (the SMS code entered on another phone) replaces the login token, so the
// first phone's token stops working. This test logs the same member in twice and calls the real login-token check.
class SingleDeviceLoginTest extends CouncilTestCase
{
    // what the token check of every logged-in API call answers for this token: 200 = let in, 401 = invalid token
    private function statusFor(?string $token): int
    {
        $req = Request::create('/api/v2/my-profile', 'POST');
        if ($token !== null) {
            $req->headers->set('token', $token);
        }
        $res = app(MyAuthV2::class)->handle($req, fn ($r) => response('ok', 200));

        return $res->getStatusCode();
    }

    // the member asks for a code on a phone and types it: returns the token that phone now holds
    private function loginOn(int $userId, string $newToken): string
    {
        DB::table('app_users')->where('id', $userId)->update([
            'pending_token' => $newToken, 'verification_nb' => '246810', 'verification_expires_at' => now()->addMinutes(10),
        ]);
        $result = app(RegistrationRepository::class)->completeVerification(['token' => $newToken, 'pin' => '246810'], '10.1.1.1');
        $this->assertArrayHasKey('user', $result, json_encode($result));

        return $newToken;
    }

    public function test_the_second_phone_logs_in_and_the_first_phone_is_logged_out(): void
    {
        $id = $this->makeAppUser('T one device', '5800000777');
        DB::table('app_users')->where('id', $id)->update(['verified' => 1, 'member_status' => 1]);

        $phoneA = $this->loginOn($id, 'TOKEN-PHONE-A');
        $this->assertSame(200, $this->statusFor($phoneA), 'phone A works after its own login');

        $phoneB = $this->loginOn($id, 'TOKEN-PHONE-B');
        $this->assertSame(200, $this->statusFor($phoneB), 'phone B works after ITS login');
        $this->assertSame(401, $this->statusFor($phoneA), 'phone A is now logged out: its token no longer works');
    }

    public function test_only_one_token_exists_per_member_and_asking_for_a_code_alone_does_not_log_the_first_phone_out(): void
    {
        $id = $this->makeAppUser('T one token', '5800000778');
        DB::table('app_users')->where('id', $id)->update(['verified' => 1, 'member_status' => 1]);
        $phoneA = $this->loginOn($id, 'TOKEN-A2');

        // phone B only ASKS for a code (does not type it): phone A keeps working
        DB::table('app_users')->where('id', $id)->update(['pending_token' => 'TOKEN-B2', 'verification_nb' => '111222', 'verification_expires_at' => now()->addMinutes(10)]);
        $this->assertSame(200, $this->statusFor($phoneA), 'a stranger asking for a code cannot kick the member out');
        $this->assertSame(401, $this->statusFor('TOKEN-B2'), 'a waiting token is not a login');

        $this->assertSame(1, DB::table('app_users')->where('member_id', '5800000778')->whereNull('deleted_at')->count(), 'one account, one token');
    }
}
