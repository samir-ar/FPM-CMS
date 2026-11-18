<?php

namespace Tests\Feature;

use App\AppUser;
use App\Http\Repositories\UserRepository;
use App\Http\Repositories\V2\RegistrationRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Feature\NationalCouncil\CouncilTestCase;

// Which app account a login belongs to: the MEMBER's own account, reused whatever phone it was created with, and
// never another member's account just because they share a phone number.
class LoginAccountLookupTest extends CouncilTestCase
{
    private function lookup(string $phone, string $memberId)
    {
        return app(UserRepository::class)->getUserByPhoneNumber($phone, $memberId);
    }

    public function test_a_member_on_a_shared_phone_does_not_get_the_other_members_account(): void
    {
        $a = $this->makeAppUser('T member A', '5601000001', ['phone_number' => '96170000001']);

        // member B shares the phone: no account of B exists, so nothing is returned (a new one will be created)
        $this->assertNull($this->lookup('96170000001', '5601000002'));
        $this->assertSame('5601000001', AppUser::find($a)->member_id, 'A\'s account is untouched');
    }

    public function test_the_members_own_account_is_found_even_after_a_phone_change_or_with_a_leading_zero(): void
    {
        $a = $this->makeAppUser('T member A', '5601000003', ['phone_number' => '96170000003']);

        $this->assertSame($a, $this->lookup('96171111111', '5601000003')->id, 'new phone number, same member: same account');
        $this->assertSame($a, $this->lookup('+96170000003', '05601000003')->id, 'leading zero on the id, "+" on the phone');

        $z = $this->makeAppUser('T zero-padded', '05601000004', ['phone_number' => '96170000004']);
        $this->assertSame($z, $this->lookup('96170000004', '5601000004')->id, 'stored with a leading zero, asked without');
    }

    public function test_an_active_account_beats_a_deleted_one_and_a_deleted_one_is_restored_only_when_alone(): void
    {
        $active = $this->makeAppUser('T active', '5601000006', ['phone_number' => '96170000006']);
        $trashed = $this->makeAppUser('T trashed', '5601000006', ['phone_number' => '96170000066', 'deleted_at' => now(), 'updated_at' => now()->addMinute()]);

        $this->assertSame($active, $this->lookup('96170000066', '5601000006')->id, 'active wins even if the deleted one has the phone');
        $this->assertNotNull(DB::table('app_users')->where('id', $trashed)->value('deleted_at'), 'the deleted one stays deleted');

        $only = $this->makeAppUser('T only trashed', '5601000007', ['phone_number' => '96170000007', 'deleted_at' => now()]);
        $this->assertSame($only, $this->lookup('96170000007', '5601000007')->id);
        $this->assertNull(DB::table('app_users')->where('id', $only)->value('deleted_at'), 'restored because the member had no active account');
    }

    public function test_logging_in_with_a_new_phone_reuses_the_account_instead_of_creating_another(): void
    {
        $fpm = DB::table('fpm_users')->whereNotNull('MobileNumber')->where('MobileNumber', '!=', '')->where('member_status', 1)->orderBy('id')->first();
        if (!$fpm) {
            $this->markTestSkipped('Needs an active fpm_users row with a mobile number.');
        }
        $member = (string) $fpm->MemberId;
        $phone = (string) $fpm->MobileNumber;
        RateLimiter::clear('otp-send:' . ltrim($member, '0'));

        // clear every account of this member, whichever way the id is written (some members have a leading-zero twin)
        DB::table('app_users')->whereRaw("TRIM(LEADING '0' FROM member_id) = ?", [ltrim($member, '0')])->update(['deleted_at' => now()]);
        $existing = $this->makeAppUser('T existing', $member, ['phone_number' => '96179999999']);   // created under an OLD number

        $this->partialMock(RegistrationRepository::class, function ($m) {
            $m->shouldAllowMockingProtectedMethods();
            $m->shouldReceive('deliverSms')->andReturnNull();
        });

        $this->postJson('/api/v2/send-verification', ['member_id' => $member, 'phone_number' => $phone, 'platform' => 'android', 'build_number' => 999999999])->assertOk();

        $this->assertSame(1, DB::table('app_users')->whereRaw("TRIM(LEADING '0' FROM member_id) = ?", [ltrim($member, '0')])->whereNull('deleted_at')->count(), 'no second account was created');
        $this->assertSame(ltrim($phone, '+'), DB::table('app_users')->where('id', $existing)->value('phone_number'), 'the account now carries the new phone');

        RateLimiter::clear('otp-send:' . ltrim($member, '0'));
    }
}
