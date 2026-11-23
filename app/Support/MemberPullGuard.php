<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

// Anti-scraping for the lists that hand back members' details in bulk (FPM Community: the directory with phone
// numbers and the posts). Same rules as the municipalities / mukhtars lists (ApiController::guardDirectoryPull):
//   - at most HOURLY_CAP lists per MEMBER per hour and DAILY_CAP per day (a person browsing never gets near it; a script does)
//   - every pull is written to the log (who, which list, which filter, which IP)
//   - one warning the moment a member passes ALERT_AT pulls in a day, so the log shows WHO is pulling that much
// Each scope has its own counters, so browsing Community never uses up someone's municipalities allowance.
// On top of this the routes require the app's request signature (app-signature middleware), which proves the call
// comes from the real app and not from a script that reuses a captured login token.
class MemberPullGuard
{
    public const HOURLY_CAP = 50;
    public const DAILY_CAP = 150;
    public const ALERT_AT = 100;

    // Returns null when the pull is allowed, or the 429 response to hand back.
    public static function check(Request $request, string $scope, string $endpoint, array $filters = []): ?JsonResponse
    {
        $user = request('user');
        $memberId = $user->member_id ?? 'unknown';
        $hourKey = $scope . '-pulls:' . $memberId;
        $dayKey = $scope . '-pulls-day:' . $memberId;
        $context = ['scope' => $scope, 'member_id' => $memberId, 'endpoint' => $endpoint, 'ip' => $request->ip()];

        if (RateLimiter::tooManyAttempts($dayKey, self::DAILY_CAP)) {
            Log::warning($scope . '_pull_daily_cap', $context);

            return self::tooMany('لقد بلغت الحد اليومي لتصفّح هذه الصفحات، يرجى المحاولة غداً.');
        }

        if (RateLimiter::tooManyAttempts($hourKey, self::HOURLY_CAP)) {
            Log::warning($scope . '_pull_rate_limited', $context);

            return self::tooMany('لقد تصفّحت عدداً كبيراً من الصفحات خلال الساعة الأخيرة، يرجى المحاولة مجدداً بعد قليل.');
        }

        RateLimiter::hit($hourKey, 3600);
        $today = RateLimiter::hit($dayKey, 86400);

        if ($today === self::ALERT_AT) {
            Log::warning($scope . '_pull_heavy_use', $context + ['pulls_today' => $today]);
        }

        Log::info($scope . '_pull', $context + $filters);

        return null;
    }

    private static function tooMany(string $message): JsonResponse
    {
        return response()->json(['error' => ['message' => $message, 'code' => 429, 'debugger' => 'too_many_requests']], 429);
    }
}
