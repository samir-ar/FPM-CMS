<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

// Extra lock on top of the normal page-perm:57 check for the Internal
// Election results tally and "who voted for whom" report - whoever is
// logged into the admin panel (even the shared Super Admin account) also
// needs a fresh OTP sent only to one hardcoded phone number before these
// two specific pages will render. Added 2026-11-13, the day before the
// 2026-11-14 Internal Election, after finding the admin panel's own
// permission system doesn't actually restrict who can see these pages to
// anyone narrower than "whoever has the Super Admin password."
class EnsureInternalElectionResultsOtp
{
    public function handle(Request $request, Closure $next)
    {
        $verifiedUntil = session('ie_results_otp_verified_until');

        if ($verifiedUntil && now()->lt($verifiedUntil)) {
            return $next($request);
        }

        return redirect()
            ->route('admin.internal-election.results-otp', ['intended' => $request->fullUrl()])
            ->with('message', 'This page requires a one-time code before it will open.');
    }
}
