<?php

namespace Tests\Feature;

use App\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Tests\TestCase;

// The real visitor's IP is recorded (logs, per-IP counters) when the request comes through the AWS load balancer,
// and a visitor can never choose his own IP by sending an X-Forwarded-For header.
class TrustedProxyClientIpTest extends TestCase
{
    protected function tearDown(): void
    {
        Request::setTrustedProxies([], Request::HEADER_X_FORWARDED_FOR);
        parent::tearDown();
    }

    private function ipSeenBy(array $server): array
    {
        $request = Request::create('/x', 'GET', [], [], [], $server);
        $seen = [];
        (new TrustProxies())->handle($request, function (Request $r) use (&$seen) {
            $seen = ['ip' => $r->ip(), 'secure' => $r->isSecure(), 'host' => $r->getHost()];

            return response('ok');
        });

        return $seen;
    }

    public function test_the_visitors_address_is_used_when_the_request_comes_from_the_load_balancer(): void
    {
        $seen = $this->ipSeenBy(['REMOTE_ADDR' => '172.31.3.114', 'HTTP_X_FORWARDED_FOR' => '203.0.113.7']);
        $this->assertSame('203.0.113.7', $seen['ip']);
    }

    public function test_a_visitor_cannot_choose_his_own_address(): void
    {
        // he sends a fake first, the load balancer appends his real address: the real one wins
        $seen = $this->ipSeenBy(['REMOTE_ADDR' => '172.31.3.114', 'HTTP_X_FORWARDED_FOR' => '6.6.6.6, 203.0.113.7']);
        $this->assertSame('203.0.113.7', $seen['ip']);

        // connecting directly (not through the balancer): the header is ignored
        $seen = $this->ipSeenBy(['REMOTE_ADDR' => '198.51.100.9', 'HTTP_X_FORWARDED_FOR' => '6.6.6.6']);
        $this->assertSame('198.51.100.9', $seen['ip']);
    }

    public function test_only_the_client_ip_header_is_trusted_not_the_scheme_or_the_host(): void
    {
        $seen = $this->ipSeenBy(['REMOTE_ADDR' => '172.31.3.114', 'HTTP_X_FORWARDED_FOR' => '203.0.113.7', 'HTTP_X_FORWARDED_PROTO' => 'https', 'HTTP_X_FORWARDED_HOST' => 'evil.example.com', 'HTTP_HOST' => 'fpmmobile.com']);
        $this->assertFalse($seen['secure'], 'the forwarded scheme stays ignored, exactly as before');
        $this->assertSame('fpmmobile.com', $seen['host'], 'the forwarded host stays ignored');
    }
}
