<?php

namespace App\Services\Analytics;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Pulls Google's classic Play Console "statistics" CSV exports directly out
 * of the Cloud Storage bucket Play auto-provisions per developer account.
 *
 * This is NOT the same thing as the Play Developer Reporting API (see
 * GooglePlayVitalsService) — Google never exposed downloads/installs or
 * store-acquisition numbers through a proper REST API, only through this
 * bucket of monthly CSVs it keeps updated. Auth here is OAuth as the Play
 * Console account itself (a refresh token obtained via a one-time manual
 * consent flow), because these buckets don't accept service-account IAM
 * grants at all — see reference_play_store_signing.md / project memory for
 * why a service account was a dead end here.
 *
 * Files are monthly, UTF-16LE encoded, comma-separated, and Google/Guzzle
 * auto-decompresses the gzip transfer encoding before we ever see the body.
 */
class GooglePlayStatsService
{
    private const STORAGE_API = 'https://storage.googleapis.com/storage/v1/b';
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    public function __construct(
        private readonly string $bucket,
        private readonly string $oauthClientPath,
        private readonly string $refreshToken,
        private readonly string $packageName,
    ) {
    }

    public static function make(): self
    {
        $config = config('services.google_play');

        return new self(
            $config['stats_bucket'],
            $config['oauth_client_path'],
            $config['stats_refresh_token'],
            $config['package_name'],
        );
    }

    private function accessToken(): string
    {
        return Cache::remember('google_play_stats:access_token', now()->addMinutes(50), function () {
            $client = json_decode(file_get_contents($this->oauthClientPath), true)['installed'];

            $response = Http::asForm()->post(self::TOKEN_URL, [
                'client_id' => $client['client_id'],
                'client_secret' => $client['client_secret'],
                'refresh_token' => $this->refreshToken,
                'grant_type' => 'refresh_token',
            ]);

            if (!$response->successful()) {
                Log::error('Google Play stats bucket OAuth refresh failed', ['body' => $response->body()]);
                $response->throw();
            }

            return $response->json()['access_token'];
        });
    }

    /**
     * Lists object names under a prefix, e.g. "stats/installs/".
     */
    public function listObjects(string $prefix): array
    {
        $names = [];
        $pageToken = null;

        do {
            $query = ['prefix' => $prefix, 'maxResults' => 1000];
            if ($pageToken) {
                $query['pageToken'] = $pageToken;
            }

            $response = Http::withToken($this->accessToken())
                ->timeout(30)
                ->get(self::STORAGE_API . "/{$this->bucket}/o", $query);
            $response->throw();

            $json = $response->json();
            foreach ($json['items'] ?? [] as $item) {
                $names[] = $item['name'];
            }
            $pageToken = $json['nextPageToken'] ?? null;
        } while ($pageToken);

        sort($names);

        return $names;
    }

    /**
     * Downloads one object and returns it as UTF-8 text. Handles both cases
     * transparently: the HTTP client auto-decompressing the gzip transfer,
     * or (if it ever doesn't) raw gzip bytes.
     */
    public function downloadText(string $objectName): string
    {
        $url = self::STORAGE_API . "/{$this->bucket}/o/" . rawurlencode($objectName) . '?alt=media';

        $response = Http::withToken($this->accessToken())->timeout(60)->get($url);
        $response->throw();

        $body = $response->body();
        $decoded = @gzdecode($body);
        if ($decoded === false) {
            $decoded = $body;
        }

        return mb_convert_encoding($decoded, 'UTF-8', 'UTF-16LE');
    }

    /**
     * Downloads and parses a CSV object into an array of associative rows.
     */
    public function downloadCsv(string $objectName): array
    {
        $text = $this->downloadText($objectName);
        // Strip a leading UTF-8 BOM if mb_convert_encoding left one in.
        $text = preg_replace('/^\x{FEFF}/u', '', $text);

        $lines = array_filter(explode("\n", str_replace("\r\n", "\n", $text)), fn($l) => trim($l) !== '');

        $rows = [];
        $header = null;
        foreach ($lines as $line) {
            $cols = str_getcsv($line);
            if ($header === null) {
                $header = $cols;
                continue;
            }
            $rows[] = array_combine($header, $cols);
        }

        return $rows;
    }

    /**
     * Finds and downloads the "overview" CSV (daily installs/uninstalls/
     * active installs) for a given month, e.g. month="202610".
     */
    public function installsOverview(string $yearMonth): array
    {
        $object = "stats/installs/installs_{$this->packageName}_{$yearMonth}_overview.csv";

        return $this->downloadCsv($object);
    }

    /**
     * Finds and downloads this month's + last month's installs overview,
     * merged, so "last 30 days" style queries work across a month boundary.
     */
    public function recentInstallsOverview(): array
    {
        $current = now()->format('Ym');
        $previous = now()->subMonth()->format('Ym');

        $rows = [];
        foreach ([$previous, $current] as $month) {
            try {
                $rows = array_merge($rows, $this->installsOverview($month));
            } catch (\Throwable $e) {
                // That month's file may not exist yet (e.g. very start of a
                // new month before Google has generated it) — skip it.
                continue;
            }
        }

        return $rows;
    }

    /**
     * Store acquisitions by country for a given month.
     */
    public function storePerformanceByCountry(string $yearMonth): array
    {
        $object = "stats/store_performance/total_store_performance_{$this->packageName}_{$yearMonth}_country.csv";

        return $this->downloadCsv($object);
    }

    /**
     * Store acquisitions by traffic source for a given month.
     */
    public function storePerformanceByTrafficSource(string $yearMonth): array
    {
        $object = "stats/store_performance/total_store_performance_{$this->packageName}_{$yearMonth}_traffic_source.csv";

        return $this->downloadCsv($object);
    }
}
