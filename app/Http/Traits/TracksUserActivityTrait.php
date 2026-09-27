<?php

namespace App\Http\Traits;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

trait TracksUserActivityTrait
{
    /**
     * Records that this app_user was active today (Beirut calendar day —
     * the app's runtime default is UTC despite config('app.timezone'), same
     * gotcha as the birthday-wish job's explicit schedule timezone override).
     *
     * insertOrIgnore + the unique(app_user_id, activity_date) index means
     * only the first call of the day per user actually writes a row; every
     * later call that day is a no-op at the DB level, no read needed first.
     *
     * Deliberately swallows any failure — activity tracking must never be
     * the reason a real app request fails.
     */
    protected function recordUserActivity(int $appUserId): void
    {
        try {
            DB::table('user_activity_logs')->insertOrIgnore([
                'app_user_id' => $appUserId,
                'activity_date' => Carbon::now(config('app.timezone'))->toDateString(),
                'created_at' => Carbon::now('UTC'),
            ]);
        } catch (Throwable $e) {
            Log::info('Failed to record user activity for app_user ' . $appUserId . ': ' . $e->getMessage());
        }
    }
}
