<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

// Browser-side safety net, on every response (CMS pages, web pages and API answers alike):
//   X-Content-Type-Options: nosniff                       a browser must not guess a file type (a file can't be turned into a script)
//   X-Frame-Options: SAMEORIGIN                           no other website can show our pages inside a frame (clickjacking of the CMS login)
//   Referrer-Policy: strict-origin-when-cross-origin      page addresses (with ids / tokens) are not leaked to other sites
//   Strict-Transport-Security (production, https only)    a browser that has seen the site over https keeps using https
// No Content-Security-Policy on purpose: the CMS uses inline scripts and several CDNs, a CSP would break pages.
//
// Production sits behind an AWS load balancer that terminates https and tells us which address the visitor used
// (X-Forwarded-Proto). A visitor who arrives on plain http:// is sent to https:// (GET / HEAD) or refused (anything
// else, so a login or a form is never accepted over http). Requests without that header (health checks, direct
// calls, staging, local) are left alone.
class SecurityHeaders
{
    // 180 days; no includeSubDomains / preload (other sub-domains are not ours to decide)
    private const HSTS = 'max-age=15552000';

    public function handle(Request $request, Closure $next)
    {
        $proto = strtolower((string) $request->header('X-Forwarded-Proto'));
        $production = config('app.env') === 'production';

        if ($production && $proto === 'http') {
            if (in_array($request->getMethod(), ['GET', 'HEAD'], true)) {
                return redirect()->secure($request->getRequestUri(), 301)->header('Cache-Control', 'public, max-age=3600');
            }

            return response()->json(['error' => ['message' => 'Please use https.', 'code' => 400, 'debugger' => 'https_required']], 403);
        }

        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        if ($production && ($proto === 'https' || $request->isSecure())) {
            $response->headers->set('Strict-Transport-Security', self::HSTS);
        }

        return $response;
    }
}
