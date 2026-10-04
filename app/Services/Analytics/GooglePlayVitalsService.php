<?php

namespace App\Services\Analytics;

use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Client for the Google Play Developer Reporting API (vitals: crash rate,
 * ANR rate, etc.) — authenticated as the service account we created
 * ("fpm-play-reporting"), granted "View app information (read-only)" in
 * Play Console's Users and Permissions.
 *
 * This is a DIFFERENT API/auth path from GooglePlayStatsService (which
 * pulls installs/acquisition CSVs from a Cloud Storage bucket as the human
 * account) — this one is a proper REST API and does accept service-account
 * credentials normally.
 */
class GooglePlayVitalsService
{
    private const BASE_URL = 'https://playdeveloperreporting.googleapis.com/v1beta1';
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const SCOPE = 'https://www.googleapis.com/auth/playdeveloperreporting';

    public function __construct(
        private readonly string $credentialsPath,
        private readonly string $packageName,
    ) {
    }

    public static function make(): self
    {
        $config = config('services.google_play');

        return new self($config['credentials_path'], $config['package_name']);
    }

    private function accessToken(): string
    {
        return Cache::remember('google_play_vitals:access_token', now()->addMinutes(50), function () {
            $credentials = json_decode(file_get_contents($this->credentialsPath), true);

            $now = time();
            $jwt = JWT::encode([
                'iss' => $credentials['client_email'],
                'scope' => self::SCOPE,
                'aud' => self::TOKEN_URL,
                'iat' => $now,
                'exp' => $now + 3600,
            ], $credentials['private_key'], 'RS256');

            $response = Http::asForm()->post(self::TOKEN_URL, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);

            if (!$response->successful()) {
                Log::error('Google Play vitals service-account auth failed', ['body' => $response->body()]);
                $response->throw();
            }

            return $response->json()['access_token'];
        });
    }

    private function request(string $method, string $path, array $payload = []): array
    {
        $url = self::BASE_URL . $path;

        $response = Http::withToken($this->accessToken())->timeout(30)->{$method}($url, $payload);

        if (!$response->successful()) {
            Log::error('Google Play Developer Reporting API error', [
                'url' => $url,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            $response->throw();
        }

        return $response->json();
    }

    /**
     * Returns the schema (supported metrics/dimensions, data freshness) for
     * a given metric set resource, e.g. "crashRateMetricSet", without
     * querying actual data. Used to confirm real metric/dimension names
     * before wiring up query() calls for them.
     */
    public function describeMetricSet(string $metricSet): array
    {
        return $this->request('get', "/apps/{$this->packageName}/{$metricSet}");
    }

    /**
     * Queries a metric set for a date range.
     *
     * @param string $metricSet e.g. "crashRateMetricSet", "anrRateMetricSet"
     * @param array $metrics e.g. ["crashRate"]
     * @param array $dimensions e.g. ["versionCode"]
     */
    public function query(string $metricSet, array $metrics, array $dimensions, \DateTimeInterface $start, \DateTimeInterface $end): array
    {
        $payload = [
            'timelineSpec' => [
                'aggregationPeriod' => 'DAILY',
                'startTime' => $this->toGoogleDate($start),
                'endTime' => $this->toGoogleDate($end),
            ],
            'metrics' => $metrics,
            'dimensions' => $dimensions,
        ];

        return $this->request('post', "/apps/{$this->packageName}/{$metricSet}:query", $payload);
    }

    private function toGoogleDate(\DateTimeInterface $date): array
    {
        return [
            'year' => (int) $date->format('Y'),
            'month' => (int) $date->format('n'),
            'day' => (int) $date->format('j'),
        ];
    }
}
