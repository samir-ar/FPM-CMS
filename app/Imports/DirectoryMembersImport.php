<?php

namespace App\Imports;

use App\V2\BusinessType;
use App\V2\DirectoryMember;
use App\Services\GeocodingService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;

class DirectoryMembersImport implements ToCollection
{
    public $imported = 0;
    public $skipped = 0;

    public function collection(Collection $rows)
    {
        // A large sheet with many unique towns can take a while to geocode
        // (Nominatim's usage policy caps requests to ~1/second) — don't let
        // PHP's default execution time limit kill the import partway
        // through.
        set_time_limit(0);
        $geocoder = new GeocodingService();

        // BusinessType::name is a translatable (JSON-stored) column, so a
        // raw where('name', ...) query can never match a plain string —
        // build the lookup from hydrated models (which resolve translations
        // via accessors) keyed by both the Arabic and English names.
        $categoryLookup = [];
        foreach (BusinessType::all() as $type) {
            $categoryLookup[$type->getTranslation('name', 'ar')] = $type;
            $categoryLookup[$type->getTranslation('name', 'en')] = $type;
        }

        foreach ($rows as $i => $row) {
            // Row 0 is the header row.
            if ($i === 0) {
                continue;
            }

            $categoryName = trim((string) ($row[0] ?? ''));
            $name = trim((string) ($row[1] ?? ''));
            $specialty = trim((string) ($row[2] ?? ''));
            $phone = trim((string) ($row[3] ?? ''));
            $country = trim((string) ($row[4] ?? ''));
            $governorate = trim((string) ($row[5] ?? ''));
            $district = trim((string) ($row[6] ?? ''));
            $town = trim((string) ($row[7] ?? ''));
            $syndicateNumber = trim((string) ($row[8] ?? ''));
            $order = trim((string) ($row[9] ?? ''));

            if ($categoryName === '' || $name === '') {
                $this->skipped++;
                continue;
            }

            $category = $categoryLookup[$categoryName] ?? null;

            if (!$category) {
                $this->skipped++;
                continue;
            }

            $coords = $geocoder->geocodeParts($town ?: null, $district ?: null, $governorate ?: null, $country ?: null);

            DirectoryMember::create([
                'business_type_id' => $category->id,
                'name' => $name,
                'specialty' => $specialty ?: null,
                'phone' => $phone ?: null,
                'country' => $country ?: null,
                'governorate' => $governorate ?: null,
                'district' => $district ?: null,
                'town' => $town ?: null,
                'syndicate_number' => $syndicateNumber ?: null,
                'latitude' => $coords['lat'] ?? null,
                'longitude' => $coords['lng'] ?? null,
                'order' => $order !== '' ? (int) $order : (DirectoryMember::max('order') ?? 0) + 1,
            ]);

            $this->imported++;
        }
    }
}
