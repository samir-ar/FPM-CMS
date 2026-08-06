<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class GeocodingService
{
    /**
     * Geocode a free-text address into [lat, lng] using OpenStreetMap's
     * Nominatim (free, no API key). Results are cached by the exact
     * address string so a bulk import with many people sharing the same
     * town/district only ever geocodes each unique address once — both to
     * stay within Nominatim's usage policy (max ~1 request/second) and to
     * keep large imports from taking forever.
     *
     * Returns null if the address is empty or couldn't be resolved.
     */
    public function geocode(?string $address): ?array
    {
        $address = trim((string) $address);
        if ($address === '') {
            return null;
        }

        $cacheKey = 'geocode:' . md5($address);

        return Cache::remember($cacheKey, now()->addDays(30), function () use ($address) {
            try {
                $response = Http::withHeaders([
                    // Nominatim's usage policy requires a descriptive
                    // User-Agent identifying the application.
                    'User-Agent' => 'FPM-CMS-Directory/1.0 (contact: admin@fpmlb.org)',
                ])->timeout(8)->get('https://nominatim.openstreetmap.org/search', [
                    'q' => $address,
                    'format' => 'json',
                    'limit' => 1,
                ]);

                // Be a good citizen of the free public Nominatim instance —
                // its policy asks for no more than ~1 request/second.
                usleep(1_100_000);

                if (!$response->successful()) {
                    return null;
                }

                $results = $response->json();
                if (empty($results)) {
                    return null;
                }

                return [
                    'lat' => (float) $results[0]['lat'],
                    'lng' => (float) $results[0]['lon'],
                ];
            } catch (\Throwable $e) {
                return null;
            }
        });
    }

    /**
     * Geocode from individual location fields (town, district, governorate,
     * country — most specific first). Real-world data entry is messy (a
     * town/district combination that doesn't quite match how OSM has it
     * indexed), so this tries the full combined address first and, if that
     * comes back empty, progressively drops the most specific part and
     * retries with the broader remainder until something resolves or
     * nothing's left.
     */
    public function geocodeParts(?string $town, ?string $district, ?string $governorate, ?string $country): ?array
    {
        $parts = array_values(array_filter(
            [$town, $district, $governorate, $country],
            fn($p) => trim((string) $p) !== ''
        ));

        while (!empty($parts)) {
            $coords = $this->geocode(implode(', ', $parts));
            if ($coords !== null) {
                return $coords;
            }
            array_shift($parts);
        }

        return null;
    }
}
