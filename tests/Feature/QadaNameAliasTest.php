<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Feature\NationalCouncil\CouncilTestCase;

// The "My municipality" / "My mukhtar" cards send "قضاء " + the member's PROFILE district. Some profile districts are
// stored under another name in the municipality / mukhtar tables (found 2026-11-23: about 6,000 members saw empty cards).
class QadaNameAliasTest extends CouncilTestCase
{
    private const TOKEN = 'T-QADA-ALIAS-TOKEN-0001';

    private $memberId;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.api_hmac_secret' => 'test-secret']);

        $this->memberId = $this->sampleMemberIds(1)[0];
        DB::table('app_users')->whereRaw("TRIM(LEADING '0' FROM member_id) = ?", [ltrim((string) $this->memberId, '0')])->update(['deleted_at' => now()]);
        $this->makeAppUser('T qada alias', (string) $this->memberId, ['token' => self::TOKEN, 'verified' => 1, 'member_status' => 1, 'deleted_at' => null]);
        RateLimiter::clear('directory-pulls:' . $this->memberId);
        RateLimiter::clear('directory-pulls-day:' . $this->memberId);
        RateLimiter::clear('app-sig-ip:127.0.0.1');
    }

    protected function tearDown(): void
    {
        // these two tables do not roll back with the transaction on this machine: remove our rows explicitly
        DB::table('municipality_members')->where('municipality_name', 'like', 'ZQ%')->delete();
        DB::table('mukhtars')->where('village_name', 'like', 'ZQ%')->delete();
        RateLimiter::clear('directory-pulls:' . $this->memberId);
        RateLimiter::clear('directory-pulls-day:' . $this->memberId);
        RateLimiter::clear('app-sig-ip:127.0.0.1');
        parent::tearDown();
    }

    private function signedCall(string $endpoint, array $params)
    {
        $ts = (string) time();
        $nonce = bin2hex(random_bytes(8));
        ksort($params);
        $parts = [];
        foreach ($params as $k => $v) {
            $parts[] = $k . '=' . $v;
        }
        $canonical = "POST\napi/v2/$endpoint\n$ts\n$nonce\n" . implode('&', $parts);
        $headers = ['token' => self::TOKEN, 'X-Timestamp' => $ts, 'X-Nonce' => $nonce, 'X-Signature' => hash_hmac('sha256', $canonical, 'test-secret')];

        return $this->postJson("/api/v2/$endpoint", $params, $headers)->assertOk()->json();
    }

    private function muni(string $qada, string $name): void
    {
        DB::table('municipality_members')->insert(['qada' => $qada, 'municipality_name' => $name, 'full_name' => 'ZQ رئيس', 'position' => 'رئيس', 'created_at' => now(), 'updated_at' => now()]);
    }

    private function mukhtar(string $qada, string $village): void
    {
        DB::table('mukhtars')->insert(['qada' => $qada, 'village_name' => $village, 'full_name' => 'ZQ مختار', 'position' => 'مختار', 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_the_profile_names_that_differ_from_the_stored_ones_now_find_their_rows(): void
    {
        $this->muni('قضاء المتن', 'ZQبرمانا');
        $this->mukhtar('قضاء المتن', 'ZQبرمانا');
        $this->muni('بيروت', 'ZQبيروت');
        $this->mukhtar('بيروت', 'ZQبيروت');
        $this->muni('المنية-الضنية', 'ZQالمنية');
        $this->mukhtar('المنية-الضنية', 'ZQالمنية');

        // what the app sends for these profile districts
        foreach ([['قضاء المتن الشمالي', 'ZQبرمانا'], ['قضاء بيروت الاولى', 'ZQبيروت'], ['قضاء بيروت الثانية', 'ZQبيروت'], ['قضاء المنية-الضنية', 'ZQالمنية']] as [$qada, $town]) {
            $members = $this->signedCall('municipality-members', ['qada' => $qada, 'municipality' => $town]);
            $this->assertCount(1, $members, "municipality for $qada");
            $mukhtars = $this->signedCall('mukhtars', ['qada' => $qada, 'village' => $town]);
            $this->assertCount(1, $mukhtars, "mukhtar for $qada");
            $this->assertContains($town, $this->signedCall('municipalities', ['qada' => $qada]), "municipality list for $qada");
            $this->assertContains($town, $this->signedCall('mukhtar-villages', ['qada' => $qada]), "village list for $qada");
        }
    }

    public function test_the_names_the_app_already_sends_still_work_and_an_unknown_name_stays_empty(): void
    {
        $this->muni('قضاء المتن', 'ZQبرمانا');

        $this->assertCount(1, $this->signedCall('municipality-members', ['qada' => 'قضاء المتن', 'municipality' => 'ZQبرمانا']));
        $this->assertCount(0, $this->signedCall('municipality-members', ['qada' => 'قضاء غير موجود', 'municipality' => 'ZQبرمانا']));
    }
}
