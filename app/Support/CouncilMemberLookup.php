<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

// Shared name / phone / member-id search over fpm_users (the TWH-synced
// member table), same matching rules as the Check-In Events attendance
// search, optionally restricted to a fixed set of member ids.
class CouncilMemberLookup
{
    // Digits only, spaces and separators removed: Excel sheets carry card
    // numbers like "26 052 00129" or " 2022 12020001".
    // Real member ids are stored as numbers (fpm_users.MemberId is a bigint), so
    // they never carry a leading zero: "01 00100235" in a sheet is member
    // 100100235.
    public static function normalizeMemberId($value): string
    {
        $digits = preg_replace('/\D+/', '', (string) $value);

        return $digits === '' ? '' : (ltrim($digits, '0') === '' ? '0' : ltrim($digits, '0'));
    }

    // The photo of a COUNCIL member as an image response (404 when he is not on the roster or has no
    // photo). For the admin pages only. fpm_users.PersonImage normally holds raw JPEG/PNG bytes, sometimes
    // base64 text.
    public static function photoResponse($memberId)
    {
        abort_unless(\App\V2\CouncilRosterMember::where('member_id', (string) $memberId)->exists(), 404);

        $bytes = DB::table('fpm_users')->where('MemberId', $memberId)->value('PersonImage');
        abort_if(empty($bytes), 404);

        if (strncmp($bytes, "\xFF\xD8", 2) !== 0 && strncmp($bytes, "\x89PNG", 4) !== 0) {
            $decoded = base64_decode(preg_replace('#^data:image/[a-z]+;base64,#i', '', trim($bytes)), true);
            abort_if($decoded === false || $decoded === '', 404);
            $bytes = $decoded;
        }

        return response($bytes, 200)
            ->header('Content-Type', strncmp($bytes, "\x89PNG", 4) === 0 ? 'image/png' : 'image/jpeg')
            ->header('Cache-Control', 'private, max-age=3600');
    }

    public static function search(string $q, ?array $onlyMemberIds = null, int $limit = 10): array
    {
        $q = trim($q);
        if (mb_strlen($q) < 2) {
            return [];
        }

        $query = DB::table('fpm_users')->where(function ($w) use ($q) {
            $w->where('UserFullName', 'like', "%{$q}%")
              ->orWhereRaw("TRIM(LEADING '+' FROM MobileNumber) LIKE ?", ['%' . ltrim($q, '+') . '%']);
            if (ctype_digit($q)) {
                $w->orWhere('MemberId', $q);
            }
        });

        if ($onlyMemberIds !== null) {
            if (empty($onlyMemberIds)) {
                return [];
            }
            $query->whereIn('MemberId', $onlyMemberIds);
        }

        return $query->limit($limit)->get(['MemberId', 'UserFullName', 'MobileNumber'])
            ->map(fn($m) => [
                'member_id' => (string) $m->MemberId,
                'name' => $m->UserFullName,
                'phone' => $m->MobileNumber,
            ])->all();
    }

    // name/phone for a set of member ids, keyed by member id.
    public static function detailsFor(array $memberIds): array
    {
        if (empty($memberIds)) {
            return [];
        }

        return DB::table('fpm_users')->whereIn('MemberId', $memberIds)
            ->get(['MemberId', 'UserFullName', 'MobileNumber', 'district', 'town', 'LastUnitPosition'])
            ->keyBy(fn($m) => (string) $m->MemberId)->all();
    }
}
