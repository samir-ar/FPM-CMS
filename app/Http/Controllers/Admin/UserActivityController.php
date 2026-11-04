<?php

namespace App\Http\Controllers\Admin;

use Carbon\Carbon;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class UserActivityController extends Controller
{
    /** The date this feature started logging — everything before it is simply unknown, not zero. */
    private const TRACKING_STARTED_AT = '2026-09-24';

    public function index()
    {
        $today = Carbon::now(config('app.timezone'))->toDateString();

        $totalMembers = DB::table('app_users')->whereNull('deleted_at')->count();

        // Week/Month/Year are true calendar-period resets (since Monday /
        // since the 1st / since Jan 1), not rolling trailing windows -
        // matches what the tile labels actually say, so "This Week" really
        // does reset every Monday instead of just sliding forward daily.
        // Only "Active (Last 10 Years)" stays a rolling window, per its own
        // "(Last N)" label.
        $dau = $this->distinctActiveSince($today);
        // "New" = members whose very first ever logged activity (across all
        // time, not just since today) is today - i.e. first-time-active
        // under tracking, not necessarily a brand-new app_users registration
        // (someone could've registered months ago and just now opened the
        // app for the first time - that's still "new" in the sense that
        // matters here: never-before-seen by this activity system).
        $newToday = DB::table('user_activity_logs')
            ->join('app_users', 'app_users.id', '=', 'user_activity_logs.app_user_id')
            ->whereNull('app_users.deleted_at')
            ->select('app_users.member_id')
            ->groupBy('app_users.member_id')
            ->havingRaw('MIN(user_activity_logs.activity_date) = ?', [$today])
            ->get()
            ->count();
        $wau = $this->distinctActiveSince(Carbon::parse($today)->startOfWeek(Carbon::MONDAY)->toDateString());
        $mau = $this->distinctActiveSince(Carbon::parse($today)->startOfMonth()->toDateString());
        $yau = $this->distinctActiveSince(Carbon::parse($today)->startOfYear()->toDateString());
        // Tracking only started 2026-09-24 (after this/any prior calendar
        // year's Jan 1), so this currently shows the same number as "Active
        // This Year" - that's expected, not a bug. It's here so the
        // dashboard doesn't need code changes once real multi-year history
        // exists.
        $tenYearActive = $this->distinctActiveSince(Carbon::parse($today)->subDays(3650)->toDateString());

        // Daily trend, last 30 days — filled with zeros for days with no activity so the chart doesn't skip gaps.
        // Joined/filtered the same as distinctActiveSince() above, so a
        // deleted member's past activity doesn't inflate this chart either.
        // Counts DISTINCT member_id, not app_user_id - a real person with
        // two duplicate app_users accounts (a known recurring issue on this
        // table) would otherwise count as 2 active people instead of 1.
        $trendRaw = DB::table('user_activity_logs')
            ->join('app_users', 'app_users.id', '=', 'user_activity_logs.app_user_id')
            ->whereNull('app_users.deleted_at')
            ->select('activity_date', DB::raw('COUNT(DISTINCT app_users.member_id) as active_count'))
            ->where('activity_date', '>=', Carbon::parse($today)->subDays(29)->toDateString())
            ->groupBy('activity_date')
            ->pluck('active_count', 'activity_date');

        $trendLabels = [];
        $trendValues = [];
        for ($i = 29; $i >= 0; $i--) {
            $date = Carbon::parse($today)->subDays($i)->toDateString();
            $trendLabels[] = Carbon::parse($date)->format('M j');
            $trendValues[] = (int) ($trendRaw[$date] ?? 0);
        }

        // "Periodically active" trend — for each of the last 30 days, count everyone active at ANY
        // point in the trailing 30-day window ending that day (not just that exact day). This is what
        // correctly counts someone who opens the app every couple of weeks as "active" throughout their
        // normal rhythm, instead of the daily chart showing them as gone on every day in between.
        $windowSize = 30;
        $trendDays = 30;
        $lookbackStart = Carbon::parse($today)->subDays($trendDays - 1 + $windowSize - 1)->toDateString();
        // member_id, not app_user_id - same duplicate-account reasoning as
        // the trend chart above, so this window count matches it exactly.
        $rowsByDate = DB::table('user_activity_logs')
            ->join('app_users', 'app_users.id', '=', 'user_activity_logs.app_user_id')
            ->whereNull('app_users.deleted_at')
            ->select('activity_date', 'app_users.member_id')
            ->where('activity_date', '>=', $lookbackStart)
            ->distinct()
            ->get()
            ->groupBy(fn($r) => $r->activity_date);

        $userCountsInWindow = []; // member_id => how many of their active-dates are currently in the window
        $periodicLabels = [];
        $periodicValues = [];

        // Prime the very first window fully (every day in [lookbackStart .. today-(trendDays-1)]),
        // then slide forward one day at a time — adding the day that enters, expiring the one that
        // falls out the back — rather than re-scanning the whole window on every step.
        $firstWindowEnd = Carbon::parse($today)->subDays($trendDays - 1);
        $firstWindowStart = $firstWindowEnd->copy()->subDays($windowSize - 1);
        for ($d = $firstWindowStart->copy(); $d->lte($firstWindowEnd); $d->addDay()) {
            foreach ($rowsByDate[$d->toDateString()] ?? [] as $row) {
                $userCountsInWindow[$row->member_id] = ($userCountsInWindow[$row->member_id] ?? 0) + 1;
            }
        }
        $periodicLabels[] = $firstWindowEnd->format('M j');
        $periodicValues[] = count($userCountsInWindow);

        for ($i = $trendDays - 2; $i >= 0; $i--) {
            $windowEnd = Carbon::parse($today)->subDays($i);
            $windowStart = $windowEnd->copy()->subDays($windowSize - 1);

            foreach ($rowsByDate[$windowEnd->toDateString()] ?? [] as $row) {
                $userCountsInWindow[$row->member_id] = ($userCountsInWindow[$row->member_id] ?? 0) + 1;
            }

            $expiredDate = $windowStart->copy()->subDay()->toDateString();
            foreach ($rowsByDate[$expiredDate] ?? [] as $row) {
                if (isset($userCountsInWindow[$row->member_id])) {
                    $userCountsInWindow[$row->member_id]--;
                    if ($userCountsInWindow[$row->member_id] <= 0) {
                        unset($userCountsInWindow[$row->member_id]);
                    }
                }
            }

            $periodicLabels[] = $windowEnd->format('M j');
            $periodicValues[] = count($userCountsInWindow);
        }

        // Frequency buckets: how many distinct days each currently-active-in-last-30 member showed up.
        // Grouped by member_id (real person), not app_user_id - and
        // COUNT(DISTINCT activity_date), not COUNT(*), since a member with
        // two duplicate accounts could otherwise have the same day counted
        // twice if both accounts logged activity on it.
        $frequencyCounts = DB::table('user_activity_logs')
            ->join('app_users', 'app_users.id', '=', 'user_activity_logs.app_user_id')
            ->whereNull('app_users.deleted_at')
            ->select('app_users.member_id', DB::raw('COUNT(DISTINCT activity_date) as days_active'))
            ->where('activity_date', '>=', Carbon::parse($today)->subDays(29)->toDateString())
            ->groupBy('app_users.member_id')
            ->pluck('days_active');

        $powerUsers = $frequencyCounts->filter(fn($c) => $c >= 20)->count();
        $regularUsers = $frequencyCounts->filter(fn($c) => $c >= 5 && $c < 20)->count();
        $casualUsers = $frequencyCounts->filter(fn($c) => $c < 5)->count();

        // Most engaged — by total distinct active days since tracking began.
        // Grouped by member_id, not app_users.id/name - a member with two
        // duplicate accounts would otherwise appear as two separate rows
        // here, each with a partial day count, instead of one row with
        // their combined (deduplicated) activity. COUNT(DISTINCT
        // activity_date), not COUNT(*), so a day both accounts were active
        // on isn't counted twice. name uses MAX() since it's not part of
        // the grouping key but every row sharing a member_id should have
        // the same name anyway.
        $mostEngaged = DB::table('user_activity_logs')
            ->join('app_users', 'app_users.id', '=', 'user_activity_logs.app_user_id')
            ->select(
                'app_users.member_id',
                DB::raw('MAX(app_users.name) as name'),
                DB::raw('COUNT(DISTINCT activity_date) as days_active'),
                DB::raw('MAX(activity_date) as last_active')
            )
            ->whereNull('app_users.deleted_at')
            ->groupBy('app_users.member_id')
            ->orderByDesc('days_active')
            ->limit(20)
            ->get();

        // Dormant — registered members with no activity in the last 30 days (or ever). Only meaningful
        // once tracking has been running a while; on day one this will correctly show almost everyone,
        // since we simply don't know anything about anyone yet.
        //
        // Grouped by member_id, taking MAX(last_active) across all of a
        // member's non-deleted accounts - otherwise a member with two
        // accounts could show up here as "dormant" via their stale account
        // even though they're genuinely active via the other one.
        $dormant = DB::table('app_users')
            ->leftJoin(DB::raw('(SELECT app_user_id, MAX(activity_date) as last_active FROM user_activity_logs GROUP BY app_user_id) as ua'), 'ua.app_user_id', '=', 'app_users.id')
            ->select(
                'app_users.member_id',
                DB::raw('MAX(app_users.name) as name'),
                DB::raw('MAX(ua.last_active) as last_active')
            )
            ->whereNull('app_users.deleted_at')
            ->groupBy('app_users.member_id')
            ->havingRaw('last_active IS NULL OR last_active < ?', [Carbon::parse($today)->subDays(30)->toDateString()])
            ->orderBy('last_active')
            ->limit(50)
            ->get();

        return view('cms.user_activity.index', compact(
            'totalMembers', 'dau', 'newToday', 'wau', 'mau', 'yau', 'tenYearActive',
            'trendLabels', 'trendValues',
            'periodicLabels', 'periodicValues',
            'powerUsers', 'regularUsers', 'casualUsers',
            'mostEngaged', 'dormant'
        ))->with('trackingStartedAt', self::TRACKING_STARTED_AT)
          ->with('pageTitle', 'User Activity');
    }

    private function distinctActiveSince(string $sinceDate): int
    {
        // Joined against app_users and filtered the same way the popup's
        // members() list is, below - otherwise a member whose account gets
        // deleted after their activity was logged still counts toward this
        // tile's total but is correctly excluded from the list you see when
        // you click into it, so the two numbers silently drift apart.
        //
        // Counts DISTINCT member_id, not app_user_id - a real person can
        // have two separate app_users account rows (a known recurring
        // duplicate-account issue on this table, see app-users:dedupe) that
        // would otherwise count as 2 active people instead of 1. Confirmed
        // on production 2026-11-02: by account_id=2299, by member_id=2278,
        // matching the popup's member-grouped count exactly.
        return DB::table('user_activity_logs')
            ->join('app_users', 'app_users.id', '=', 'user_activity_logs.app_user_id')
            ->whereNull('app_users.deleted_at')
            ->where('user_activity_logs.activity_date', '>=', $sinceDate)
            ->distinct('app_users.member_id')
            ->count('app_users.member_id');
    }

    /** Member list behind a DAU/WAU/MAU/YAU tile, for the "click to see who" popup. */
    public function members(string $period)
    {
        $today = Carbon::now(config('app.timezone'))->toDateString();

        $sinceDate = match ($period) {
            'today' => $today,
            'week'  => Carbon::parse($today)->startOfWeek(Carbon::MONDAY)->toDateString(),
            'month' => Carbon::parse($today)->startOfMonth()->toDateString(),
            'year'  => Carbon::parse($today)->startOfYear()->toDateString(),
            'decade' => Carbon::parse($today)->subDays(3650)->toDateString(),
            default => null,
        };

        if (!$sinceDate) {
            return response()->json(['error' => 'Invalid period'], 400);
        }

        // district/town come from fpm_users, not app_users — app_users'
        // own copies of those columns aren't reliably kept in sync.
        // last_active uses created_at (a real timestamp) rather than
        // activity_date (date-only) - one log row per member per day, so
        // this is the moment their most recent day's activity was first
        // recorded, the closest real time-of-day data actually tracked.
        $members = DB::table('user_activity_logs')
            ->join('app_users', 'app_users.id', '=', 'user_activity_logs.app_user_id')
            ->leftJoin('fpm_users', 'fpm_users.MemberId', '=', 'app_users.member_id')
            ->select(
                'app_users.member_id',
                'app_users.name',
                'fpm_users.district',
                'fpm_users.town',
                DB::raw('MAX(user_activity_logs.created_at) as last_active')
            )
            ->whereNull('app_users.deleted_at')
            ->where('user_activity_logs.activity_date', '>=', $sinceDate)
            ->groupBy('app_users.member_id', 'app_users.name', 'fpm_users.district', 'fpm_users.town')
            ->orderByDesc('last_active')
            ->get();

        // last_active is stored in UTC (see TracksUserActivityTrait) -
        // convert to Beirut time for display, same as everywhere else an
        // admin-facing timestamp is shown. Sort order above is unaffected
        // since a fixed-offset conversion preserves relative ordering.
        $members->each(function ($m) {
            if ($m->last_active) {
                $m->last_active = Carbon::parse($m->last_active, 'UTC')
                    ->setTimezone(config('app.timezone'))
                    ->toDateTimeString();
            }
        });

        return response()->json($members);
    }
}
