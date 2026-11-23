<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Feature\NationalCouncil\CouncilTestCase;

// FPM Community (the directory with phone numbers and the posts) is protected like the elections and the
// municipalities: a logged-in member's SIGNED request only, and the two lists that hand back members' details are
// capped per member and logged. Its counters are separate from the municipalities ones.
class CommunityScrapingProtectionTest extends CouncilTestCase
{
    private const TOKEN = 'T-COMMUNITY-TOKEN-0001';

    private $memberId;
    private $typeId;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.api_hmac_secret' => 'test-secret']);

        $this->typeId = DB::table('business_types')->value('id');
        $this->assertNotNull($this->typeId, 'the test needs one business type');

        $this->memberId = $this->sampleMemberIds(1)[0];
        DB::table('app_users')->whereRaw("TRIM(LEADING '0' FROM member_id) = ?", [ltrim((string) $this->memberId, '0')])->update(['deleted_at' => now()]);
        $this->makeAppUser('T community reader', (string) $this->memberId, ['token' => self::TOKEN, 'verified' => 1, 'member_status' => 1, 'deleted_at' => null]);
        $this->clearCounters();
    }

    protected function tearDown(): void
    {
        $this->clearCounters();
        parent::tearDown();
    }

    private function clearCounters(): void
    {
        foreach (['community-pulls:', 'community-pulls-day:', 'directory-pulls:', 'directory-pulls-day:'] as $prefix) {
            RateLimiter::clear($prefix . $this->memberId);
        }
        RateLimiter::clear('app-sig-ip:127.0.0.1');
    }

    // exactly what the app's interceptor / VerifyAppSignature compute
    private function signHeaders(string $endpoint, array $params): array
    {
        $ts = (string) time();
        $nonce = bin2hex(random_bytes(8));
        ksort($params);
        $parts = [];
        foreach ($params as $k => $v) {
            $parts[] = $k . '=' . $v;
        }
        $canonical = "POST\napi/v2/$endpoint\n$ts\n$nonce\n" . implode('&', $parts);

        return ['X-Timestamp' => $ts, 'X-Nonce' => $nonce, 'X-Signature' => hash_hmac('sha256', $canonical, 'test-secret')];
    }

    private function endpoints(): array
    {
        return [
            'business-type' => [],
            'community-districts' => [],
            'get-community' => [],
            'directory-members' => ['business_type_id' => $this->typeId],
        ];
    }

    public function test_every_community_list_refuses_anonymous_and_unsigned_requests_and_answers_a_signed_member_request(): void
    {
        foreach ($this->endpoints() as $endpoint => $params) {
            $uri = "/api/v2/$endpoint";

            // no login, even with a valid signature
            $this->postJson($uri, $params, $this->signHeaders($endpoint, $params))->assertStatus(401);

            // a valid login but no signature (a script reusing a captured token): refused
            $this->postJson($uri, $params, ['token' => self::TOKEN])->assertStatus(403);

            // a wrong signature
            $bad = $this->signHeaders($endpoint, $params);
            $bad['X-Signature'] = str_repeat('0', 64);
            $this->postJson($uri, $params, $bad + ['token' => self::TOKEN])->assertStatus(403);

            // the real app: login + signature
            $this->postJson($uri, $params, $this->signHeaders($endpoint, $params) + ['token' => self::TOKEN])->assertOk();
        }
    }

    public function test_a_signature_cannot_be_used_twice(): void
    {
        $params = ['business_type_id' => $this->typeId];
        $headers = $this->signHeaders('directory-members', $params) + ['token' => self::TOKEN];

        $this->postJson('/api/v2/directory-members', $params, $headers)->assertOk();
        $this->postJson('/api/v2/directory-members', $params, $headers)->assertStatus(403);
    }

    public function test_pulls_of_the_directory_and_the_posts_are_capped_per_member_and_logged(): void
    {
        Log::spy();
        $params = ['business_type_id' => $this->typeId];
        $call = fn () => $this->postJson('/api/v2/directory-members', $params, $this->signHeaders('directory-members', $params) + ['token' => self::TOKEN]);

        $call()->assertOk();
        Log::shouldHaveReceived('info')->with('community_pull', \Mockery::on(fn ($c) => $c['member_id'] == $this->memberId && $c['endpoint'] === 'getDirectoryMembers'))->once();

        for ($i = 0; $i < 50; $i++) {
            RateLimiter::hit('community-pulls:' . $this->memberId, 3600);
        }
        $call()->assertStatus(429);
        Log::shouldHaveReceived('warning')->with('community_pull_rate_limited', \Mockery::any())->once();

        // the posts list shares the same member allowance
        $this->postJson('/api/v2/get-community', [], $this->signHeaders('get-community', []) + ['token' => self::TOKEN])->assertStatus(429);

        // the two small lookups (business types, districts) hand back no personal data and are not capped
        $this->postJson('/api/v2/business-type', [], $this->signHeaders('business-type', []) + ['token' => self::TOKEN])->assertOk();
        $this->postJson('/api/v2/community-districts', [], $this->signHeaders('community-districts', []) + ['token' => self::TOKEN])->assertOk();
    }

    public function test_the_community_allowance_is_separate_from_the_municipalities_allowance(): void
    {
        for ($i = 0; $i < 50; $i++) {
            RateLimiter::hit('community-pulls:' . $this->memberId, 3600);
        }
        $params = ['business_type_id' => $this->typeId];
        // community is used up ...
        $this->postJson('/api/v2/directory-members', $params, $this->signHeaders('directory-members', $params) + ['token' => self::TOKEN])->assertStatus(429);
        // ... but the municipalities list of the same member is not affected
        $p = ['qada' => 'T-qada'];
        $this->postJson('/api/v2/municipalities', $p, $this->signHeaders('municipalities', $p) + ['token' => self::TOKEN])->assertOk();
    }

    public function test_a_daily_cap_and_a_heavy_use_alert_catch_a_patient_scraper(): void
    {
        Log::spy();
        $params = ['business_type_id' => $this->typeId];
        $call = fn () => $this->postJson('/api/v2/directory-members', $params, $this->signHeaders('directory-members', $params) + ['token' => self::TOKEN]);

        for ($i = 0; $i < 99; $i++) {
            RateLimiter::hit('community-pulls-day:' . $this->memberId, 86400);
        }
        $call()->assertOk();
        Log::shouldHaveReceived('warning')->with('community_pull_heavy_use', \Mockery::on(fn ($c) => $c['member_id'] == $this->memberId && $c['pulls_today'] === 100))->once();

        for ($i = 0; $i < 50; $i++) {
            RateLimiter::hit('community-pulls-day:' . $this->memberId, 86400);
        }
        RateLimiter::clear('community-pulls:' . $this->memberId);
        $res = $call()->assertStatus(429);
        $this->assertStringContainsString('غداً', $res->json('error.message'));
        Log::shouldHaveReceived('warning')->with('community_pull_daily_cap', \Mockery::any())->once();
    }
}
