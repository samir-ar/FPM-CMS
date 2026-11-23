<?php

namespace Tests\Feature;

use App\Http\Middleware\CanonicalHost;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\Feature\NationalCouncil\CouncilTestCase;

// A made-up website name in the Host header must never end up in a link we build (password-reset e-mails, next-page links).
class CanonicalHostTest extends CouncilTestCase
{
    private function hostSeen(string $host, string $env = 'production'): string
    {
        config(['app.env' => $env]);
        $request = Request::create('http://' . $host . '/x');
        $request->headers->set('host', $host);
        $seen = '';
        (new CanonicalHost())->handle($request, function (Request $r) use (&$seen) {
            $seen = $r->getHost();

            return response('ok');
        });

        return $seen;
    }

    public function test_an_unknown_name_is_replaced_by_the_real_one_in_production(): void
    {
        $this->assertSame('fpmmobile.com', $this->hostSeen('evil.example.com'));
        $this->assertSame('fpmmobile.com', $this->hostSeen('evil.example.com:8443'));
        $this->assertSame('fpmmobile.com', $this->hostSeen('fpmmobile.com.evil.example.com'));
    }

    public function test_the_real_names_the_balancer_checks_and_local_calls_are_left_alone(): void
    {
        foreach (['fpmmobile.com', 'www.fpmmobile.com', '172.31.33.120', '10.0.2.15', '192.168.1.5', '127.0.0.1', 'localhost'] as $host) {
            $this->assertSame($host, $this->hostSeen($host), $host);
        }
    }

    public function test_staging_and_local_are_untouched(): void
    {
        $this->assertSame('evil.example.com', $this->hostSeen('evil.example.com', 'staging'));
        $this->assertSame('evil.example.com', $this->hostSeen('evil.example.com', 'local'));
    }

    // end to end through the real middleware stack (Kernel): what the application sees as "the host" for a foreign name
    public function test_the_whole_stack_hands_the_application_the_real_host(): void
    {
        config(['app.env' => 'production']);
        Route::get('/__host_probe', fn () => response(request()->getHost()));

        $this->get('http://evil.example.com/__host_probe')->assertOk()->assertSee('fpmmobile.com', false);
        $this->get('http://www.fpmmobile.com/__host_probe')->assertOk()->assertSee('www.fpmmobile.com', false);
    }
}
