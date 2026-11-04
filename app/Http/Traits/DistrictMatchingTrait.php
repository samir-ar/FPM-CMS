<?php

namespace App\Http\Traits;

// Matches a voter's raw fpm_users.district text against a set of
// election_states.name values. Needed because the two never line up with
// a plain == - normalizes whitespace/alef forms, then compares each
// SEPARATOR-SPLIT PART of the member's district for an exact match, not
// a raw substring. A member's district can be a combined value like
// "المنية-الضنية" even though election_states stores "المنية" and
// "الضنية" as two separate rows, so splitting on common separators and
// exact-matching each resulting part is needed for that case. A plain
// str_contains() (the previous approach) is too permissive for this:
// "جبيل" is a real election_states row, and also a genuine substring of
// the totally unrelated "بنت جبيل" - a member in بنت جبيل would
// wrongly match a جبيل-only election. Splitting first avoids that,
// since "بنت جبيل" has no separator to split on and so never collapses
// down to just "جبيل".
trait DistrictMatchingTrait
{
    private function normalizeDistrictName(string $s): string
    {
        $s = trim(preg_replace('/\s+/u', ' ', $s));
        return str_replace(['أ', 'إ', 'آ'], 'ا', $s);
    }

    private function districtMatchesAnyState(?string $memberDistrict, array $stateNames): bool
    {
        if (!$memberDistrict) {
            return false;
        }

        $parts = preg_split('/[\-–\/،,]+/u', $memberDistrict) ?: [];
        $parts[] = $memberDistrict; // the un-split value, for a plain single-word district
        $normalizedParts = array_map(fn($p) => $this->normalizeDistrictName($p), $parts);

        foreach ($stateNames as $name) {
            if (in_array($this->normalizeDistrictName($name), $normalizedParts, true)) {
                return true;
            }
        }
        return false;
    }
}
