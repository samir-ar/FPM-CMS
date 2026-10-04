<?php

namespace App\Services\Analytics;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Aggregates the Android (Play Console) and iOS (App Store Connect) data
 * sources into one summary array for the admin dashboard.
 *
 * The two platforms genuinely don't expose the same metrics (see
 * project_next_steps.md / the dashboard build notes) — Android has no
 * programmatic access to impressions/conversion rate, iOS has no simple
 * "active install base" number — so this intentionally returns different
 * fields per platform rather than forcing a fake parity between them.
 *
 * Everything is cached for an hour: these all hit either a CSV download, a
 * paid-quota analytics API, or an OAuth token refresh, and the admin
 * dashboard doesn't need to the minute.
 */
class AppAnalyticsDashboardService
{
    private const CACHE_TTL_MINUTES = 60;

    public function summary(): array
    {
        return [
            'android' => $this->androidSummary(),
            'ios' => $this->iosSummary(),
            'generated_at' => now()->toDateTimeString(),
        ];
    }

    private function androidSummary(): array
    {
        return Cache::remember('analytics_dashboard:android', now()->addMinutes(self::CACHE_TTL_MINUTES), function () {
            try {
                $stats = GooglePlayStatsService::make();
                $rows = $stats->recentInstallsOverview();

                $last7 = collect($rows)->filter(fn($r) => Carbon::parse($r['Date'])->gte(now()->subDays(7)));
                $last30 = collect($rows)->filter(fn($r) => Carbon::parse($r['Date'])->gte(now()->subDays(30)));
                $latest = collect($rows)->sortByDesc('Date')->first();

                $dailyTrend = collect($rows)
                    ->sortBy('Date')
                    ->map(fn($r) => [
                        'date' => $r['Date'],
                        'installs' => (int) $r['Daily Device Installs'],
                        'uninstalls' => (int) $r['Daily Device Uninstalls'],
                    ])
                    ->values()
                    ->all();

                $vitals = $this->androidVitals();

                return [
                    'available' => true,
                    'latest_data_date' => $latest['Date'] ?? null,
                    'installs_7d' => (int) $last7->sum('Daily Device Installs'),
                    'uninstalls_7d' => (int) $last7->sum('Daily Device Uninstalls'),
                    'installs_30d' => (int) $last30->sum('Daily Device Installs'),
                    'uninstalls_30d' => (int) $last30->sum('Daily Device Uninstalls'),
                    'active_installs' => $latest ? (int) $latest['Active Device Installs'] : null,
                    'daily_trend' => array_slice($dailyTrend, -30),
                    'crash_rate_7d_avg' => $vitals['crash_rate_7d_avg'],
                    'anr_rate_7d_avg' => $vitals['anr_rate_7d_avg'],
                ];
            } catch (\Throwable $e) {
                Log::error('Android analytics summary failed', ['error' => $e->getMessage()]);

                return ['available' => false, 'error' => $e->getMessage()];
            }
        });
    }

    private function androidVitals(): array
    {
        try {
            $vitals = GooglePlayVitalsService::make();
            $start = now()->subDays(8);
            $end = now()->subDays(1);

            $crash = $vitals->query('crashRateMetricSet', ['crashRate'], [], $start, $end);
            $anr = $vitals->query('anrRateMetricSet', ['anrRate'], [], $start, $end);

            return [
                'crash_rate_7d_avg' => $this->averageMetricValue($crash, 'crashRate'),
                'anr_rate_7d_avg' => $this->averageMetricValue($anr, 'anrRate'),
            ];
        } catch (\Throwable $e) {
            Log::warning('Android vitals fetch failed', ['error' => $e->getMessage()]);

            return ['crash_rate_7d_avg' => null, 'anr_rate_7d_avg' => null];
        }
    }

    private function averageMetricValue(array $response, string $metricName): ?float
    {
        $values = [];
        foreach ($response['rows'] ?? [] as $row) {
            foreach ($row['metrics'] ?? [] as $m) {
                if (($m['metric'] ?? null) === $metricName && isset($m['decimalValue']['value'])) {
                    $values[] = (float) $m['decimalValue']['value'];
                }
            }
        }

        return empty($values) ? null : round(array_sum($values) / count($values), 4);
    }

    private function iosSummary(): array
    {
        return Cache::remember('analytics_dashboard:ios', now()->addMinutes(self::CACHE_TTL_MINUTES), function () {
            try {
                $service = AppStoreConnectService::make();
                $requestId = $service->ensureOngoingReportRequest();

                $downloadsData = $this->latestIosReportData($service, $requestId, 'App Store Installation and Deletion Standard');
                $engagementData = $this->latestIosReportData($service, $requestId, 'App Store Discovery and Engagement Standard');

                if ($downloadsData === null && $engagementData === null) {
                    return [
                        'available' => false,
                        'pending_first_report' => true,
                    ];
                }

                return [
                    'available' => true,
                    'downloads' => $downloadsData,
                    'engagement' => $engagementData,
                ];
            } catch (\Throwable $e) {
                Log::error('iOS analytics summary failed', ['error' => $e->getMessage()]);

                return ['available' => false, 'error' => $e->getMessage()];
            }
        });
    }

    /**
     * Finds the most recent daily instance of a named report and returns
     * its parsed rows, or null if no instance has been generated yet
     * (expected for the first 24–48h after the ONGOING request is created).
     */
    private function latestIosReportData(AppStoreConnectService $service, string $requestId, string $reportName): ?array
    {
        $reports = $service->listReports($requestId);
        $report = collect($reports)->firstWhere('name', $reportName);
        if (!$report) {
            return null;
        }

        $instances = $service->listInstances($report['id']);
        if (empty($instances)) {
            return null;
        }

        $latestInstance = collect($instances)->sortByDesc('processingDate')->first();
        $segments = $service->listSegments($latestInstance['id']);
        if (empty($segments)) {
            return null;
        }

        $rows = [];
        foreach ($segments as $segment) {
            if ($segment['url']) {
                $rows = array_merge($rows, $service->downloadSegment($segment['url']));
            }
        }

        return [
            'processing_date' => $latestInstance['processingDate'],
            'rows' => $rows,
        ];
    }
}
