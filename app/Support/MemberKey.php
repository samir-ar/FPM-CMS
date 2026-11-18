<?php

namespace App\Support;

// The member id in the form used for the "one vote per member" database rules: leading zeros stripped, so
// 0100100129 and 100100129 are the same member. Null when there is no member id (a deleted account).
class MemberKey
{
    public static function from($memberId): ?string
    {
        $key = ltrim(trim((string) $memberId), '0');

        return $key === '' ? null : $key;
    }
}
