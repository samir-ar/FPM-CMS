<?php

namespace Tests\Feature;

use Tests\Feature\NationalCouncil\CouncilTestCase;

// 2026-11-23 hardening: browser security headers, plain http -> https, a clean answer for a wrong token,
// and the removal of the old open payment / donation routes and of the old optional api/* block.
class SecurityHardeningTest extends CouncilTestCase
{
    public function test_every_response_carries_the_browser_security_headers(): void
    {
        foreach (['/login', '/api/v2/app-versions'] as $uri) {
            $res = $uri === '/login' ? $this->get($uri) : $this->postJson($uri, []);
            $this->assertSame('nosniff', $res->headers->get('X-Content-Type-Options'), $uri);
            $this->assertSame('SAMEORIGIN', $res->headers->get('X-Frame-Options'), $uri);
            $this->assertSame('strict-origin-when-cross-origin', $res->headers->get('Referrer-Policy'), $uri);
        }
    }

    public function test_hsts_and_the_https_redirect_only_apply_in_production(): void
    {
        // not production (local / staging / tests): nothing redirects, no HSTS
        $res = $this->withHeaders(['X-Forwarded-Proto' => 'http'])->get('/login');
        $res->assertOk();
        $this->assertNull($res->headers->get('Strict-Transport-Security'));

        config(['app.env' => 'production']);

        // a browser that arrives on plain http:// is sent to https://
        $res = $this->withHeaders(['X-Forwarded-Proto' => 'http'])->get('/login?x=1');
        $res->assertStatus(301);
        $this->assertStringStartsWith('https://', $res->headers->get('Location'));
        $this->assertStringEndsWith('/login?x=1', $res->headers->get('Location'));

        // a form / API call over plain http is refused, never accepted
        $this->withHeaders(['X-Forwarded-Proto' => 'http'])->postJson('/api/v2/app-versions', [])->assertStatus(403)->assertJsonPath('error.debugger', 'https_required');

        // over https the page works and tells the browser to keep using https
        $res = $this->withHeaders(['X-Forwarded-Proto' => 'https'])->get('/login');
        $res->assertOk();
        $this->assertStringContainsString('max-age=', $res->headers->get('Strict-Transport-Security'));

        // no header at all (load balancer health check, direct call): left alone
        $this->get('/login')->assertOk();
    }

    public function test_a_wrong_login_token_on_an_optional_route_gets_a_clean_message(): void
    {
        $res = $this->withHeaders(['token' => 'not-a-real-token'])->postJson('/api/v2/get-wall-feed', []);

        $res->assertStatus(400)->assertJsonPath('error.code', 100)->assertJsonPath('error.debugger', 'invalid_token');
        $this->assertSame('Invalid Access Token', $res->json('error.message'));
        $this->assertStringNotContainsString('property', (string) $res->getContent());
    }

    public function test_the_old_payment_donation_and_legacy_optional_routes_are_gone(): void
    {
        foreach (['api/e-bill', 'api/i-pay', 'api/news', 'api/paginated-news', 'api/important-links', 'api/webviews', 'api/v2/e-bill', 'api/v2/i-pay', 'donations/store'] as $uri) {
            $this->postJson('/' . $uri, [])->assertNotFound();
        }
        $this->get('/donations')->assertNotFound();
        $this->get('/payment-mobile-response/1/x')->assertNotFound();
    }

    public function test_what_the_app_needs_from_that_area_still_works(): void
    {
        // the news "next page" link now points at the v2 route (the old api/ one is gone)
        $this->assertStringEndsWith('/api/v2/paginated-news', route('api.news.paginated'));

        $this->postJson('/api/v2/important-links', [])->assertOk();
        $this->postJson('/api/v2/webviews', [])->assertOk();
        $this->postJson('/api/v2/get-wall-feed', [])->assertOk();
    }
}
