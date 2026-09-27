<?php

namespace App\Http\Controllers\Admin;

use Carbon\Carbon;
use App\Http\Controllers\Controller;
use App\V2\News;
use Illuminate\Support\Facades\DB;

class ContentEngagementController extends Controller
{
    /** user_shares only exists from this date forward — no retroactive share history. */
    private const SHARE_TRACKING_STARTED_AT = '2026-09-27';

    public function index()
    {
        $today = Carbon::now(config('app.timezone'))->toDateString();

        // One optional date range, applied to all four Top-20 lists below —
        // not the trend charts. Empty/absent means "all time" (default).
        $dateFrom = request()->filled('date_from') ? request('date_from') : null;
        $dateTo = request()->filled('date_to') ? request('date_to') : null;
        $hasDateFilter = $dateFrom || $dateTo;

        $totalLikes = DB::table('users_news')->count();
        $totalShares = (int) News::sum('shares');

        // Top liked articles — filtering by date means "liked within this
        // range" (each like has a real timestamp), regardless of when the
        // article itself was posted.
        $topLiked = News::withCount(['users' => function ($q) use ($dateFrom, $dateTo) {
                if ($dateFrom) $q->where('users_news.created_at', '>=', $dateFrom);
                if ($dateTo) $q->where('users_news.created_at', '<=', $dateTo . ' 23:59:59');
            }])
            ->having('users_count', '>', 0)
            ->orderByDesc('users_count')
            ->limit(20)
            ->get(['id', 'title', 'created_at']);

        // Top shared articles: with no date filter, use the all-time running
        // counter (news.shares) as before. With a filter applied, switch to
        // the user_shares log instead — the only source with real timestamps
        // — which only has data from SHARE_TRACKING_STARTED_AT onward.
        if ($hasDateFilter) {
            $sharesQuery = DB::table('user_shares')
                ->join('news', 'news.id', '=', 'user_shares.news_id')
                ->select('news.id', 'news.title', 'news.created_at', DB::raw('COUNT(*) as shares'));
            if ($dateFrom) $sharesQuery->where('user_shares.created_at', '>=', $dateFrom);
            if ($dateTo) $sharesQuery->where('user_shares.created_at', '<=', $dateTo . ' 23:59:59');
            $topShared = $sharesQuery->groupBy('news.id', 'news.title', 'news.created_at')
                ->orderByDesc('shares')
                ->limit(20)
                ->get();
        } else {
            $topShared = News::orderByDesc('shares')
                ->limit(20)
                ->get(['id', 'title', 'shares', 'created_at']);
        }

        // Daily likes trend, last 30 days — zero-filled so the chart doesn't skip gaps.
        $trendRaw = DB::table('users_news')
            ->select(DB::raw('DATE(created_at) as like_date'), DB::raw('COUNT(*) as like_count'))
            ->where('created_at', '>=', Carbon::parse($today)->subDays(29)->toDateString())
            ->groupBy('like_date')
            ->pluck('like_count', 'like_date');

        $trendLabels = [];
        $trendValues = [];
        for ($i = 29; $i >= 0; $i--) {
            $date = Carbon::parse($today)->subDays($i)->toDateString();
            $trendLabels[] = Carbon::parse($date)->format('M j');
            $trendValues[] = (int) ($trendRaw[$date] ?? 0);
        }

        // Likes by month, last 12 months — a genuine trend, since each like
        // row has its own real timestamp.
        $monthlyLikesRaw = DB::table('users_news')
            ->select(DB::raw("DATE_FORMAT(created_at, '%Y-%m') as ym"), DB::raw('COUNT(*) as cnt'))
            ->where('created_at', '>=', Carbon::parse($today)->startOfMonth()->subMonths(11)->toDateString())
            ->groupBy('ym')
            ->pluck('cnt', 'ym');

        // Shares by month is NOT "when the share happened" — that data
        // doesn't exist, shares are just a running total per article. This
        // is instead "total shares of articles POSTED that month", which is
        // the closest honest monthly breakdown the current schema allows.
        $monthlySharesRaw = DB::table('news')
            ->select(DB::raw("DATE_FORMAT(created_at, '%Y-%m') as ym"), DB::raw('SUM(shares) as total'))
            ->where('created_at', '>=', Carbon::parse($today)->startOfMonth()->subMonths(11)->toDateString())
            ->groupBy('ym')
            ->pluck('total', 'ym');

        $monthlyLabels = [];
        $monthlyLikesValues = [];
        $monthlySharesValues = [];
        for ($i = 11; $i >= 0; $i--) {
            $month = Carbon::parse($today)->startOfMonth()->subMonths($i);
            $ym = $month->format('Y-m');
            $monthlyLabels[] = $month->format('M Y');
            $monthlyLikesValues[] = (int) ($monthlyLikesRaw[$ym] ?? 0);
            $monthlySharesValues[] = (int) ($monthlySharesRaw[$ym] ?? 0);
        }

        $topLikingUsersQuery = DB::table('users_news')
            ->join('app_users', 'app_users.id', '=', 'users_news.user_id')
            ->select('app_users.id', 'app_users.name', 'app_users.member_id', 'app_users.district', 'app_users.town', DB::raw('COUNT(*) as like_count'));
        if ($dateFrom) $topLikingUsersQuery->where('users_news.created_at', '>=', $dateFrom);
        if ($dateTo) $topLikingUsersQuery->where('users_news.created_at', '<=', $dateTo . ' 23:59:59');
        $topLikingUsers = $topLikingUsersQuery
            ->groupBy('app_users.id', 'app_users.name', 'app_users.member_id', 'app_users.district', 'app_users.town')
            ->orderByDesc('like_count')
            ->limit(20)
            ->get();

        // Only reflects shares made since user_shares started tracking —
        // no retroactive history exists, same limitation as User Activity.
        $topSharingUsersQuery = DB::table('user_shares')
            ->join('app_users', 'app_users.id', '=', 'user_shares.user_id')
            ->select('app_users.id', 'app_users.name', 'app_users.member_id', 'app_users.district', 'app_users.town', DB::raw('COUNT(*) as share_count'));
        if ($dateFrom) $topSharingUsersQuery->where('user_shares.created_at', '>=', $dateFrom);
        if ($dateTo) $topSharingUsersQuery->where('user_shares.created_at', '<=', $dateTo . ' 23:59:59');
        $topSharingUsers = $topSharingUsersQuery
            ->groupBy('app_users.id', 'app_users.name', 'app_users.member_id', 'app_users.district', 'app_users.town')
            ->orderByDesc('share_count')
            ->limit(20)
            ->get();

        $shareTrackingStartedAt = self::SHARE_TRACKING_STARTED_AT;

        // Top 5 districts by likes/shares, broken down per month — last 12
        // months, one series per district. Shares here only reflects
        // user_shares (tracking started today), so it'll be sparse until
        // more history accumulates — same caveat as everywhere else shares
        // are shown by time.
        [$districtLikesLabels, $districtLikesSeries] = $this->topDistrictsByMonth(
            'users_news', 'users_news.user_id', $today
        );
        [$districtSharesLabels, $districtSharesSeries] = $this->topDistrictsByMonth(
            'user_shares', 'user_shares.user_id', $today
        );

        // Reshaped into the array-of-objects-per-month format Morris.Bar
        // expects, here in PHP rather than inline in Blade — @json() doesn't
        // reliably parse multi-line chained expressions as its argument.
        $districtLikesChartData = $this->toMorrisSeries($districtLikesLabels, $districtLikesSeries);
        $districtSharesChartData = $this->toMorrisSeries($districtSharesLabels, $districtSharesSeries);

        return view('cms.content_engagement.index', compact(
            'totalLikes', 'totalShares', 'topLiked', 'topShared',
            'trendLabels', 'trendValues',
            'monthlyLabels', 'monthlyLikesValues', 'monthlySharesValues',
            'topLikingUsers', 'topSharingUsers',
            'dateFrom', 'dateTo', 'hasDateFilter', 'shareTrackingStartedAt',
            'districtLikesSeries', 'districtSharesSeries',
            'districtLikesChartData', 'districtSharesChartData'
        ))->with([
            'layout' => 'layouts.cms',
            'pageTitle' => 'Content Engagement',
        ]);
    }

    // [district => [12 monthly values]] -> [{month, district1: v, district2: v, ...}, ...],
    // the shape Morris.Bar needs for a multi-series chart.
    private function toMorrisSeries(array $labels, array $series): array
    {
        $rows = [];
        foreach ($labels as $i => $label) {
            $row = ['month' => $label];
            foreach ($series as $district => $values) {
                $row[$district] = $values[$i];
            }
            $rows[] = $row;
        }
        return $rows;
    }

    // Builds "top 5 districts, broken down per month for the last 12 months"
    // for either likes (users_news) or shares (user_shares) — same shape of
    // query either way, just a different action table.
    private function topDistrictsByMonth(string $actionTable, string $userIdColumn, string $today): array
    {
        $windowStart = Carbon::parse($today)->startOfMonth()->subMonths(11)->toDateString();

        // Top 5 districts overall within this same 12-month window.
        $topDistricts = DB::table($actionTable)
            ->join('app_users', 'app_users.id', '=', $userIdColumn)
            ->whereNotNull('app_users.district')
            ->where($actionTable . '.created_at', '>=', $windowStart)
            ->select('app_users.district', DB::raw('COUNT(*) as total'))
            ->groupBy('app_users.district')
            ->orderByDesc('total')
            ->limit(5)
            ->pluck('district')
            ->all();

        $monthlyLabels = [];
        for ($i = 11; $i >= 0; $i--) {
            $monthlyLabels[] = Carbon::parse($today)->startOfMonth()->subMonths($i)->format('M Y');
        }

        if (empty($topDistricts)) {
            return [$monthlyLabels, []];
        }

        $raw = DB::table($actionTable)
            ->join('app_users', 'app_users.id', '=', $userIdColumn)
            ->whereIn('app_users.district', $topDistricts)
            ->where($actionTable . '.created_at', '>=', $windowStart)
            ->select(
                'app_users.district',
                DB::raw("DATE_FORMAT($actionTable.created_at, '%Y-%m') as ym"),
                DB::raw('COUNT(*) as cnt')
            )
            ->groupBy('app_users.district', 'ym')
            ->get()
            ->groupBy('district');

        $series = [];
        foreach ($topDistricts as $district) {
            $byMonth = $raw->get($district, collect())->pluck('cnt', 'ym');
            $values = [];
            for ($i = 11; $i >= 0; $i--) {
                $ym = Carbon::parse($today)->startOfMonth()->subMonths($i)->format('Y-m');
                $values[] = (int) ($byMonth[$ym] ?? 0);
            }
            $series[$district] = $values;
        }

        return [$monthlyLabels, $series];
    }

    // Per-article breakdown: exactly who liked it and when. Shares have no
    // per-user tracking (just a running total on the article), so there's
    // nothing equivalent to show for shares here.
    public function show($id)
    {
        $article = News::findOrFail($id);

        $likedBy = $article->users()
            ->select('app_users.id', 'app_users.name', 'app_users.member_id', 'users_news.created_at as liked_at')
            ->orderByDesc('users_news.created_at')
            ->get();

        return view('cms.content_engagement.show', compact('article', 'likedBy'))->with([
            'layout' => 'layouts.cms',
            'pageTitle' => 'Engagement — ' . $article->title,
        ]);
    }
}
