<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

// Proves a request actually came from the real app, not a script replaying
// a captured login token. The Flutter app signs every request with a secret
// embedded at build time (see AppConstants.apiSignatureSecret) — computing
// a valid signature requires either that exact secret or decompiling the
// app to extract it, which rules out casual/lazy scraping even though it
// can't stop a fully determined reverse-engineering effort (that's what
// real device attestation, Play Integrity / App Attest, would be for).
//
// Applied only to endpoints that return real member PII in bulk (Internal
// Election's voter/candidate lists, مخاتير/بلديات contact lists) — not
// blanket across the whole API, since a bug here fails closed for every
// request it covers.
class VerifyAppSignature
{
    private const MAX_CLOCK_SKEW_SECONDS = 120;

    // Generic per-IP cap on top of whatever each endpoint's own rate
    // limiting does (district-voters has its own member-keyed limit
    // separately) — this is the only thing standing between the
    // مخاتير/بلديات endpoints and rapid-fire requests, since they're
    // unauthenticated and have no member_id to key a limit on.
    private const MAX_REQUESTS_PER_MINUTE = 20;

    public function handle(Request $request, Closure $next)
    {
        $secret = config('app.api_hmac_secret');

        if (!$secret) {
            // Misconfigured environment (secret not set) — fail closed
            // rather than silently accepting unsigned requests.
            return response()->json(['error' => ['message' => 'Server misconfigured']], 500);
        }

        $timestamp = $request->header('X-Timestamp');
        $signature = $request->header('X-Signature');

        // Deliberately 403, never 401 — this app treats any 401 anywhere as
        // "the login token is invalid" and force-logs the user out
        // (ErrorInterceptor). A signature problem is a different failure
        // entirely and must never be able to masquerade as that.
        if (!$timestamp || !$signature || !ctype_digit($timestamp)) {
            return response()->json(['error' => ['message' => 'Missing or invalid request signature']], 403);
        }

        if (abs(time() - (int) $timestamp) > self::MAX_CLOCK_SKEW_SECONDS) {
            return response()->json(['error' => ['message' => 'Request signature expired']], 403);
        }

        // The matched route's own URI pattern (e.g. "api/v2/mukhtars")
        // rather than $request->path() — the latter would differ between
        // local's folder-based URL (.../CMS/public/api/v2/...) and
        // production's clean domain, breaking signing inconsistently per
        // environment. The route's URI is the same regardless of how the
        // webserver maps the URL to get here.
        //
        // Also covers every request param (query + body, sorted by key) —
        // without this, a captured signature could be replayed with a
        // different page number / village / قضاء and still pass, since the
        // signature never looked at what was actually being asked for.
        $canonical = strtoupper($request->method()) . "\n"
            . $request->route()->uri() . "\n"
            . $timestamp . "\n"
            . $this->canonicalParams($request);
        $expected = hash_hmac('sha256', $canonical, $secret);

        if (!hash_equals($expected, $signature)) {
            return response()->json(['error' => ['message' => 'Invalid request signature']], 403);
        }

        // Use-once check — a signature is only valid for its single first
        // use, not for its whole time window. Keyed on the signature itself
        // (not the request contents) and only remembered for slightly
        // longer than the clock-skew window, since anything older than
        // that already gets rejected above regardless — so this cache
        // never has to grow without bound.
        $nonceKey = 'app-sig-used:' . $signature;
        if (Cache::has($nonceKey)) {
            return response()->json(['error' => ['message' => 'Request signature already used']], 403);
        }
        Cache::put($nonceKey, true, self::MAX_CLOCK_SKEW_SECONDS + 10);

        // Generic per-IP rate limit — the only thing pacing requests on
        // endpoints with no logged-in member to key a limit on
        // (مخاتير/بلديات are fully public). district-voters already has
        // its own, stricter, member-keyed limit elsewhere; this is just an
        // extra floor under everything this middleware guards.
        $ipLimitKey = 'app-sig-ip:' . $request->ip();
        if (RateLimiter::tooManyAttempts($ipLimitKey, self::MAX_REQUESTS_PER_MINUTE)) {
            return response()->json(['error' => ['message' => 'لقد تجاوزت الحد المسموح من الطلبات، يرجى المحاولة لاحقاً']], 429);
        }
        RateLimiter::hit($ipLimitKey, 60);

        return $next($request);
    }

    // Scalar-looking but still server-injected, never sent by the app
    // itself — SetLanguage merges this before this middleware runs, from
    // the Language/Accept-Language headers, not from the app's own signed
    // params. (Short list on purpose — see the type filter below for why
    // this doesn't also need every *non*-scalar injected key listed here.)
    private const EXCLUDED_PARAMS = ['language'];

    // All request input (query string for GET, form body for POST),
    // sorted by key so both sides build the exact same string regardless
    // of the order params happened to be added in.
    //
    // Two layers of filtering, for two different failure modes:
    //  - By type (is_scalar): several upstream middlewares merge whole
    //    Eloquent models into the request before this one runs
    //    (FpmCheckToken's 'loggedInUser', MyAuthV2's 'user', a Collection
    //    under 'groups_info'). Casting one of those to a string crashes
    //    outright — PHP implicitly calls the model's toJson(), which
    //    throws if the model has binary column data (exactly what happened
    //    with FpmUser's PersonImage). This layer means a *future* middleware
    //    merging some other object in can't silently crash this again.
    //  - By name (EXCLUDED_PARAMS): catches known injected values that
    //    *are* plain scalars (so the type filter wouldn't catch them) but
    //    still aren't part of what the app actually signed.
    private function canonicalParams(Request $request): string
    {
        $params = array_filter(
            $request->except(self::EXCLUDED_PARAMS),
            fn($value) => is_scalar($value) || is_null($value)
        );
        ksort($params);

        $parts = [];
        foreach ($params as $key => $value) {
            $parts[] = $key . '=' . $value;
        }

        return implode('&', $parts);
    }
}
