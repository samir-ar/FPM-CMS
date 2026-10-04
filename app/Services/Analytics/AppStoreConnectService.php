<?php

namespace App\Services\Analytics;

use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin client for the App Store Connect API's Analytics Reports endpoints.
 *
 * Apple's analytics data isn't a simple "give me today's downloads" call —
 * it's a report-request/report/instance/segment hierarchy: you request an
 * ONGOING report (once), Apple generates daily instances of each report
 * under it going forward, and each instance exposes one or more gzipped TSV
 * segment URLs to download. See exploreReports() for discovering exactly
 * which report names/ids this app actually has before wiring up parsing
 * for specific metrics.
 */
class AppStoreConnectService
{
    private const BASE_URL = 'https://api.appstoreconnect.apple.com/v1';

    public function __construct(
        private readonly string $issuerId,
        private readonly string $keyId,
        private readonly string $privateKeyPath,
        private readonly string $appId,
    ) {
    }

    public static function make(): self
    {
        $config = config('services.app_store_connect');

        return new self(
            $config['issuer_id'],
            $config['key_id'],
            $config['private_key_path'],
            $config['app_id'],
        );
    }

    /**
     * Apple requires a fresh ES256 JWT per request window (max 20 minutes).
     * Cached just under that so we're not re-signing on every call.
     */
    private function token(): string
    {
        return Cache::remember('app_store_connect:jwt', now()->addMinutes(18), function () {
            $privateKey = file_get_contents($this->privateKeyPath);

            $payload = [
                'iss' => $this->issuerId,
                'iat' => time(),
                'exp' => time() + (19 * 60),
                'aud' => 'appstoreconnect-v1',
            ];

            return JWT::encode($payload, $privateKey, 'ES256', $this->keyId);
        });
    }

    private function request(string $method, string $path, array $query = []): array
    {
        $url = str_starts_with($path, 'http') ? $path : self::BASE_URL . $path;

        $response = Http::withToken($this->token())
            ->timeout(30)
            ->{$method}($url, $query);

        if (!$response->successful()) {
            Log::error('App Store Connect API error', [
                'url' => $url,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            $response->throw();
        }

        return $response->json();
    }

    /**
     * Find this app's existing ONGOING report request, or create one.
     * Apple allows only a single ONGOING request per app (it's not scoped
     * per category — category is a property of individual reports, filtered
     * when listing them), so we check first before creating.
     */
    public function ensureOngoingReportRequest(): string
    {
        $existing = $this->request('get', "/apps/{$this->appId}/analyticsReportRequests", [
            'filter[accessType]' => 'ONGOING',
        ]);

        if (!empty($existing['data'])) {
            return $existing['data'][0]['id'];
        }

        $created = $this->request('post', '/analyticsReportRequests', [
            'data' => [
                'type' => 'analyticsReportRequests',
                'attributes' => [
                    'accessType' => 'ONGOING',
                ],
                'relationships' => [
                    'app' => [
                        'data' => ['type' => 'apps', 'id' => $this->appId],
                    ],
                ],
            ],
        ]);

        return $created['data']['id'];
    }

    /**
     * Lists all reports under the app's ONGOING request, optionally
     * filtered to one category (APP_USAGE, APP_STORE_ENGAGEMENT,
     * APP_STORE_COMMERCE, FRAMEWORK_USAGE, PERFORMANCE).
     */
    public function listReports(string $reportRequestId, ?string $category = null): array
    {
        $query = $category !== null ? ['filter[category]' => $category] : [];
        $result = $this->request('get', "/analyticsReportRequests/{$reportRequestId}/reports", $query);

        return array_map(fn($r) => [
            'id' => $r['id'],
            'name' => $r['attributes']['name'] ?? null,
            'category' => $r['attributes']['category'] ?? null,
        ], $result['data'] ?? []);
    }

    public function listInstances(string $reportId): array
    {
        $result = $this->request('get', "/analyticsReports/{$reportId}/instances");

        return array_map(fn($i) => [
            'id' => $i['id'],
            'granularity' => $i['attributes']['granularity'] ?? null,
            'processingDate' => $i['attributes']['processingDate'] ?? null,
        ], $result['data'] ?? []);
    }

    public function listSegments(string $instanceId): array
    {
        $result = $this->request('get', "/analyticsReportInstances/{$instanceId}/segments");

        return array_map(fn($s) => [
            'url' => $s['attributes']['url'] ?? null,
            'checksum' => $s['attributes']['checksum'] ?? null,
            'sizeInBytes' => $s['attributes']['sizeInBytes'] ?? null,
        ], $result['data'] ?? []);
    }

    /**
     * Downloads a gzipped TSV segment and parses it into an array of
     * associative rows (header row becomes the keys).
     */
    public function downloadSegment(string $url): array
    {
        $response = Http::timeout(60)->get($url);
        $response->throw();

        $tsv = gzdecode($response->body());
        $lines = array_filter(explode("\n", $tsv), fn($l) => trim($l) !== '');

        $rows = [];
        $header = null;
        foreach ($lines as $line) {
            $cols = explode("\t", $line);
            if ($header === null) {
                $header = $cols;
                continue;
            }
            $rows[] = array_combine($header, $cols);
        }

        return $rows;
    }

    /**
     * Discovery helper — dumps the full report catalog (categories, report
     * names/ids) actually available for this app, so parsing logic can
     * target real names instead of guessed ones. Not used by the dashboard
     * itself; run via `php artisan analytics:apple:explore`.
     */
    public function exploreReports(array $categories): array
    {
        $requestId = $this->ensureOngoingReportRequest();
        $catalog = [];

        foreach ($categories as $category) {
            $catalog[$category] = [
                'reportRequestId' => $requestId,
                'reports' => $this->listReports($requestId, $category),
            ];
        }

        return $catalog;
    }
}
