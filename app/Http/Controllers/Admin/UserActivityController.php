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

        $dau = $this->distinctActiveSince($today);
        $wau = $this->distinctActiveSince(Carbon::parse($today)->subDays(6)->toDateString());
        $mau = $this->distinctActiveSince(Carbon::parse($today)->subDays(29)->toDateString());
        $yau = $this->distinctActiveSince(Carbon::parse($today)->subDays(364)->toDateString());

        // Daily trend, last 30 days — filled with zeros for days with no activity so the chart doesn't skip gaps.
        $trendRaw = DB::table('user_activity_logs')
            ->select('activity_date', DB::raw('COUNT(DISTINCT app_user_id) as active_count'))
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
        $rowsByDate = DB::table('user_activity_logs')
            ->select('activity_date', 'app_user_id')
            ->where('activity_date', '>=', $lookbackStart)
            ->get()
            ->groupBy(fn($r) => $r->activity_date);

        $userCountsInWindow = []; // app_user_id => how many of their active-dates are currently in the window
        $periodicLabels = [];
        $periodicValues = [];

        // Prime the very first window fully (every day in [lookbackStart .. today-(trendDays-1)]),
        // then slide forward one day at a time — adding the day that enters, expiring the one that
        // falls out the back — rather than re-scanning the whole window on every step.
        $firstWindowEnd = Carbon::parse($today)->subDays($trendDays - 1);
        $firstWindowStart = $firstWindowEnd->copy()->subDays($windowSize - 1);
        for ($d = $firstWindowStart->copy(); $d->lte($firstWindowEnd); $d->addDay()) {
            foreach ($rowsByDate[$d->toDateString()] ?? [] as $row) {
                $userCountsInWindow[$row->app_user_id] = ($userCountsInWindow[$row->app_user_id] ?? 0) + 1;
            }
        }
        $periodicLabels[] = $firstWindowEnd->format('M j');
        $periodicValues[] = count($userCountsInWindow);

        for ($i = $trendDays - 2; $i >= 0; $i--) {
            $windowEnd = Carbon::parse($today)->subDays($i);
            $windowStart = $windowEnd->copy()->subDays($windowSize - 1);

            foreach ($rowsByDate[$windowEnd->toDateString()] ?? [] as $row) {
                $userCountsInWindow[$row->app_user_id] = ($userCountsInWindow[$row->app_user_id] ?? 0) + 1;
            }

            $expiredDate = $windowStart->copy()->subDay()->toDateString();
            foreach ($rowsByDate[$expiredDate] ?? [] as $row) {
                if (isset($userCountsInWindow[$row->app_user_id])) {
                    $userCountsInWindow[$row->app_user_id]--;
                    if ($userCountsInWindow[$row->app_user_id] <= 0) {
                        unset($userCountsInWindow[$row->app_user_id]);
                    }
                }
            }

            $periodicLabels[] = $windowEnd->format('M j');
            $periodicValues[] = count($userCountsInWindow);
        }

        // Frequency buckets: how many distinct days each currently-active-in-last-30 member showed up.
        $frequencyCounts = DB::table('user_activity_logs')
            ->select('app_user_id', DB::raw('COUNT(*) as days_active'))
            ->where('activity_date', '>=', Carbon::parse($today)->subDays(29)->toDateString())
            ->groupBy('app_user_id')
            ->pluck('days_active');

        $powerUsers = $frequencyCounts->filter(fn($c) => $c >= 20)->count();
        $regularUsers = $frequencyCounts->filter(fn($c) => $c >= 5 && $c < 20)->count();
        $casualUsers = $frequencyCounts->filter(fn($c) => $c < 5)->count();

        // Most engaged — by total distinct active days since tracking began.
        $mostEngaged = DB::table('user_activity_logs')
            ->join('app_users', 'app_users.id', '=', 'user_activity_logs.app_user_id')
            ->select(
                'app_users.id',
                'app_users.name',
                'app_users.member_id',
                DB::raw('COUNT(*) as days_active'),
                DB::raw('MAX(activity_date) as last_active')
            )
            ->whereNull('app_users.deleted_at')
            ->groupBy('app_users.id', 'app_users.name', 'app_users.member_id')
            ->orderByDesc('days_active')
            ->limit(20)
            ->get();

        // Dormant — registered members with no activity in the last 30 days (or ever). Only meaningful
        // once tracking has been running a while; on day one this will correctly show almost everyone,
        // since we simply don't know anything about anyone yet.
        $dormant = DB::table('app_users')
            ->leftJoin(DB::raw('(SELECT app_user_id, MAX(activity_date) as last_active FROM user_activity_logs GROUP BY app_user_id) as ua'), 'ua.app_user_id', '=', 'app_users.id')
            ->select('app_users.id', 'app_users.name', 'app_users.member_id', 'ua.last_active')
            ->whereNull('app_users.deleted_at')
            ->where(function ($q) use ($today) {
                $q->whereNull('ua.last_active')
                    ->orWhere('ua.last_active', '<', Carbon::parse($today)->subDays(30)->toDateString());
            })
            ->orderBy('ua.last_active')
            ->limit(50)
            ->get();

        return view('cms.user_activity.index', compact(
            'totalMembers', 'dau', 'wau', 'mau', 'yau',
            'trendLabels', 'trendValues',
            'periodicLabels', 'periodicValues',
            'powerUsers', 'regularUsers', 'casualUsers',
            'mostEngaged', 'dormant'
        ))->with('trackingStartedAt', self::TRACKING_STARTED_AT)
          ->with('pageTitle', 'User Activity');
    }

    private function distinctActiveSince(string $sinceDate): int
    {
        return DB::table('user_activity_logs')
            ->where('activity_date', '>=', $sinceDate)
            ->distinct('app_user_id')
            ->count('app_user_id');
    }
}
