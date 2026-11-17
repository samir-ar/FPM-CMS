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

    // Who has REALLY used the app. An old app_users row (many date from 2020-2024) only proves an account
    // exists, so "logged in" means: recorded active (user_activity_logs, one row per user per day) since
    // activity tracking began. Returns the date tracking started and, per member id, the last day seen.
    public static function activitySince(array $memberIds): array
    {
        $since = DB::table('user_activity_logs')->min('activity_date');
        $last = [];

        if ($memberIds) {
            $rows = DB::table('user_activity_logs as l')
                ->join('app_users as u', 'u.id', '=', 'l.app_user_id')
                ->whereIn('u.member_id', $memberIds)
                ->groupBy('u.member_id')
                ->selectRaw('u.member_id as member_id, MAX(l.activity_date) as last_seen')
                ->get();
            foreach ($rows as $r) {
                $last[(string) $r->member_id] = $r->last_seen;
            }
        }

        return ['since' => $since, 'last_seen' => $last];
    }

    // The picture uploaded on the member's Representatives record (persons.image, kept in S3), as a URL.
    // It is often newer than the photo in the member list, so for the council pages it comes FIRST. Checked
    // once an hour that the file really is there, so a missing S3 file falls back to the member-list photo.
    public static function repImageUrl($memberId): ?string
    {
        $image = DB::table('persons')->where('member_id', (string) $memberId)
            ->whereNotNull('image')->where('image', '!=', '')->orderByDesc('id')->value('image');
        if (!$image) {
            return null;
        }

        $url = \Illuminate\Support\Facades\Storage::disk('s3')->url(config('app.aws_bucket_project_name') . '/storage/images/representatives/' . $image);

        $ok = \Illuminate\Support\Facades\Cache::remember('council_rep_img_ok:' . md5($url), 3600, function () use ($url) {
            try {
                return \Illuminate\Support\Facades\Http::timeout(4)->head($url)->successful();
            } catch (\Throwable $e) {
                return false;
            }
        });

        return $ok ? $url : null;
    }

    // Member ids (as strings) among $memberIds that have a photo of either kind: a Representatives
    // upload or a member-list photo.
    public static function idsWithPhoto(array $memberIds): array
    {
        if (!$memberIds) {
            return [];
        }

        $fromMembers = DB::table('fpm_users')->whereIn('MemberId', $memberIds)
            ->whereRaw('LENGTH(PersonImage) > 100')->pluck('MemberId');
        $fromReps = DB::table('persons')->whereIn('member_id', $memberIds)
            ->whereNotNull('image')->where('image', '!=', '')->pluck('member_id');

        return $fromMembers->merge($fromReps)->map(fn($i) => (string) $i)->unique()->values()->all();
    }

    // The photo of a COUNCIL member as an image response (404 when he is not on the roster or has no
    // photo). For the admin pages only. Order: the Representatives upload (usually the newer one), then
    // fpm_users.PersonImage, which normally holds raw JPEG/PNG bytes, sometimes base64 text.
    public static function photoResponse($memberId)
    {
        abort_unless(\App\V2\CouncilRosterMember::where('member_id', (string) $memberId)->exists(), 404);

        if ($url = self::repImageUrl($memberId)) {
            return redirect()->away($url)->header('Cache-Control', 'private, max-age=600');
        }

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
