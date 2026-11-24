<?php

namespace App\Support;

// Excel stores a phone like 03451111 as the NUMBER 3451111 and drops the leading zero, so the lists of
// municipality members and mukhtars were loaded with 7-digit numbers (3451111, 4964098 ...). A Lebanese number that
// is exactly 7 digits always has the 0 of its area / mobile prefix in front: 03 451 111, 04 964 098.
// Any other length (8 digits = 70 / 71 / 76 / 78 / 79 / 81 mobiles, 9-11 digits = with country code, ...) is left as it is.
class LebanesePhone
{
    public static function withLeadingZero(string $raw): string
    {
        $raw = trim($raw);
        $digits = preg_replace('/[\s\-.]/', '', $raw);   // spaces, dashes and dots written inside the number

        return preg_match('/^\d{7}$/', $digits) ? '0' . $digits : $raw;
    }
}
