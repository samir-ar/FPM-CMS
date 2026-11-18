<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Feature\NationalCouncil\CouncilTestCase;

// بلديات و مخاتير (names + phones) and التنظيم الداخلي are member-only screens in the app. The endpoints behind them
// must answer only a logged-in member's SIGNED request (like the district voters list), and pulls are capped per member.
class DirectoryEndpointsProtectionTest extends CouncilTestCase
{
    private const TOKEN = 'T-DIRECTORY-TOKEN-0001';
    private const ENDPOINTS = [
        'internal-org' => ['tab' => 'nizham_dakhili'],
        'municipalities' => ['qada' => 'T-qada'],
        'municipality-members' => ['qada' => 'T-qada', 'municipality' => 'T-municipality'],
        'mukhtar-villages' => ['qada' => 'T-qada'],
        'mukhtars' => ['qada' => 'T-qada', 'village' => 'T-village'],
    ];

    private $memberId;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.api_hmac_secret' => 'test-secret']);

        $this->memberId = $this->sampleMemberIds(1)[0];
        // one active account per member: hide this member's existing one (rolled back with the test), then add ours
        DB::table('app_users')->whereRaw("TRIM(LEADING '0' FROM member_id) = ?", [ltrim((string) $this->memberId, '0')])->update(['deleted_at' => now()]);
        $this->makeAppUser('T directory reader', (string) $this->memberId, ['token' => self::TOKEN, 'verified' => 1, 'member_status' => 1, 'deleted_at' => null]);
        RateLimiter::clear('directory-pulls:' . $this->memberId);
        RateLimiter::clear('app-sig-ip:127.0.0.1');
    }

    protected function tearDown(): void
    {
        RateLimiter::clear('directory-pulls:' . $this->memberId);
        RateLimiter::clear('app-sig-ip:127.0.0.1');
        parent::tearDown();
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

    public function test_every_endpoint_refuses_anonymous_and_unsigned_requests_and_answers_a_signed_member_request(): void
    {
        foreach (self::ENDPOINTS as $endpoint => $params) {
            $uri = "/api/v2/$endpoint";

            // no login at all, even with a valid signature: refused
            $this->postJson($uri, $params, $this->signHeaders($endpoint, $params))->assertStatus(401);

            // a valid login but no signature (a script reusing a captured token): refused
            $this->postJson($uri, $params, ['token' => self::TOKEN])->assertStatus(403);

            // a wrong signature: refused
            $bad = $this->signHeaders($endpoint, $params);
            $bad['X-Signature'] = str_repeat('0', 64);
            $this->postJson($uri, $params, $bad + ['token' => self::TOKEN])->assertStatus(403);

            // the real app: login + signature
            $this->postJson($uri, $params, $this->signHeaders($endpoint, $params) + ['token' => self::TOKEN])->assertOk();
        }
    }

    public function test_a_signature_cannot_be_used_twice(): void
    {
        $params = ['qada' => 'T-qada'];
        $headers = $this->signHeaders('municipalities', $params) + ['token' => self::TOKEN];

        $this->postJson('/api/v2/municipalities', $params, $headers)->assertOk();
        $this->postJson('/api/v2/municipalities', $params, $headers)->assertStatus(403);
    }

    public function test_pulls_are_capped_per_member_and_logged(): void
    {
        \Illuminate\Support\Facades\Log::spy();

        $params = ['qada' => 'T-qada'];
        $this->postJson('/api/v2/municipalities', $params, $this->signHeaders('municipalities', $params) + ['token' => self::TOKEN])->assertOk();
        \Illuminate\Support\Facades\Log::shouldHaveReceived('info')->with('directory_pull', \Mockery::on(fn ($ctx) => $ctx['member_id'] == $this->memberId && $ctx['endpoint'] === 'getMunicipalities'))->once();

        for ($i = 0; $i < 200; $i++) {
            RateLimiter::hit('directory-pulls:' . $this->memberId, 3600);
        }
        $this->postJson('/api/v2/municipalities', $params, $this->signHeaders('municipalities', $params) + ['token' => self::TOKEN])->assertStatus(429);
        \Illuminate\Support\Facades\Log::shouldHaveReceived('warning')->with('directory_pull_rate_limited', \Mockery::any())->once();

        // the document has no cap (it is one small text)
        $p = ['tab' => 'nizham_dakhili'];
        $this->postJson('/api/v2/internal-org', $p, $this->signHeaders('internal-org', $p) + ['token' => self::TOKEN])->assertOk();
    }
}
