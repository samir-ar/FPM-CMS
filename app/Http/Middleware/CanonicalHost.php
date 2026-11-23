<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

// Host-header poisoning: a visitor (or a script) can send ANY website name in the Host header and the server builds its
// links with it (verified on production 2026-11-23: Host: evil.example.com gave "next_page_url": "https://evil.example.com/...").
// The same would happen to the link inside a CMS password-reset e-mail.
//
// In production an unknown name is rewritten to the real one BEFORE anything builds a link, so every generated link uses
// fpmmobile.com. Nothing is rejected: the load balancer's health checks (Host = the instance's private IP), direct calls,
// the real names (and www.) are left exactly as they are. Staging and local are untouched.
// Overrides in .env: CANONICAL_HOST (default fpmmobile.com) and ALLOWED_HOSTS (extra names, comma separated).
class CanonicalHost
{
    public function handle(Request $request, Closure $next)
    {
        if (config('app.env') === 'production') {
            $canonical = strtolower((string) env('CANONICAL_HOST', 'fpmmobile.com'));
            $host = strtolower(preg_replace('/:\d+$/', '', (string) $request->headers->get('host')));

            if ($host !== '' && !$this->isAllowed($host, $canonical)) {
                $request->headers->set('host', $canonical);
                $request->server->set('HTTP_HOST', $canonical);
            }
        }

        return $next($request);
    }

    private function isAllowed(string $host, string $canonical): bool
    {
        $extra = array_filter(array_map('trim', explode(',', strtolower((string) env('ALLOWED_HOSTS', '')))));
        if (in_array($host, array_merge([$canonical, 'www.' . $canonical], $extra), true)) {
            return true;
        }

        // the balancer's health checks and direct calls inside the network: a private IPv4 address or localhost
        return $host === 'localhost'
            || (bool) preg_match('/^(10\.\d+\.\d+\.\d+|172\.(1[6-9]|2\d|3[01])\.\d+\.\d+|192\.168\.\d+\.\d+|127\.\d+\.\d+\.\d+)$/', $host);
    }
}
