<?php

namespace Tests\Feature;

use App\Support\LoginLimits;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Feature\NationalCouncil\CouncilTestCase;

// The CMS page "Login limits" (Users -> Login Limits): reset the code-request / wrong-code counters of the app login
// for one member or for everybody.
class LoginLimitsPageTest extends CouncilTestCase
{
    private const MEMBER = '987654321';

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanUp();
    }

    protected function tearDown(): void
    {
        $this->cleanUp();
        parent::tearDown();
    }

    private function cleanUp(): void
    {
        Cache::forget('login-limits-version');
        RateLimiter::clear('otp-send:' . self::MEMBER);
        RateLimiter::clear('otp-guess:' . self::MEMBER);
        RateLimiter::clear('otp-send:555');
        RateLimiter::clear('otp-ip:1.2.3.4');
    }

    private function fillCounters(string $member): void
    {
        for ($i = 0; $i < LoginLimits::SEND_MAX; $i++) {
            RateLimiter::hit(LoginLimits::sendKey($member), 900);
        }
        for ($i = 0; $i < LoginLimits::GUESS_MAX; $i++) {
            RateLimiter::hit(LoginLimits::guessKey($member), 900);
        }
    }

    public function test_the_page_shows_what_a_member_has_used(): void
    {
        $this->fillCounters(self::MEMBER);
        $html = $this->actingAs($this->adminUser(), 'admin')->get(route('admin.users.login-limits', ['member_id' => '0' . self::MEMBER], false))->assertOk()->getContent();

        $this->assertStringContainsString('5 of 5', $html);
        $this->assertStringContainsString('10 of 10', $html);
        $this->assertStringContainsString('blocked', $html);
        $this->assertStringContainsString('locked', $html);
    }

    public function test_resetting_one_member_clears_only_that_member(): void
    {
        $this->fillCounters(self::MEMBER);
        $this->fillCounters('555');

        $this->actingAs($this->adminUser(), 'admin')
            ->post(route('admin.users.login-limits.reset-member', [], false), ['member_id' => '0' . self::MEMBER])
            ->assertRedirect();

        $this->assertSame(0, LoginLimits::status(self::MEMBER)['sent']);
        $this->assertSame(0, LoginLimits::status(self::MEMBER)['wrong']);
        $this->assertSame(LoginLimits::SEND_MAX, LoginLimits::status('555')['sent'], 'another member is untouched');
    }

    public function test_resetting_everybody_clears_every_member_and_ip_counter(): void
    {
        $this->fillCounters(self::MEMBER);
        $this->fillCounters('555');
        RateLimiter::hit(LoginLimits::ipKey('1.2.3.4'), 900);

        $this->actingAs($this->adminUser(), 'admin')->post(route('admin.users.login-limits.reset-all', [], false))->assertRedirect();

        $this->assertSame(0, LoginLimits::status(self::MEMBER)['sent']);
        $this->assertSame(0, LoginLimits::status('555')['wrong']);
        $this->assertSame(0, RateLimiter::attempts(LoginLimits::ipKey('1.2.3.4')));

        // and the counters really count again afterwards
        RateLimiter::hit(LoginLimits::sendKey(self::MEMBER), 900);
        $this->assertSame(1, LoginLimits::status(self::MEMBER)['sent']);
    }

    public function test_a_bad_member_id_is_refused(): void
    {
        $this->actingAs($this->adminUser(), 'admin')
            ->post(route('admin.users.login-limits.reset-member', [], false), ['member_id' => "1; DROP"])
            ->assertSessionHasErrors('member_id');
    }
}
