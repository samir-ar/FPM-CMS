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
     * One row per (app_user_id, activity_date) via the unique index below,
     * but created_at is refreshed on every call (upsert, not insert-or-
     * ignore) - the first visit of the day creates the row, every later
     * visit that same day just bumps created_at to now, so it stays a
     * genuine "most recent activity" timestamp instead of freezing at
     * whatever time they first opened the app that day. Still only one
     * write per request either way, same low cost as before.
     *
     * Deliberately swallows any failure — activity tracking must never be
     * the reason a real app request fails.
     */
    protected function recordUserActivity(int $appUserId): void
    {
        try {
            DB::table('user_activity_logs')->upsert(
                [
                    'app_user_id' => $appUserId,
                    'activity_date' => Carbon::now(config('app.timezone'))->toDateString(),
                    'created_at' => Carbon::now('UTC'),
                ],
                ['app_user_id', 'activity_date'],
                ['created_at']
            );
        } catch (Throwable $e) {
            Log::info('Failed to record user activity for app_user ' . $appUserId . ': ' . $e->getMessage());
        }
    }
}
