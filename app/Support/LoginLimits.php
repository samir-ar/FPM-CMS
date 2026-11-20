<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

// The counters that protect the app login (how many codes a member asked for, how many wrong codes were typed, how many
// wrong codes came from one IP). They live in the server cache. This class names the keys in ONE place and lets the
// CMS page "Login limits" reset them for one member or for everybody.
//
// "Reset everybody" cannot list the keys (the file cache has no list), so every key carries a version number: raising
// the version starts every counter from zero at once. Version 0 (never reset) uses the plain keys, so the tests and
// older code that clear 'otp-send:<member>' keep working.
class LoginLimits
{
    public const SEND_MAX = 5;      // code requests per member per window
    public const SEND_WINDOW = 900; // seconds (15 minutes)
    public const GUESS_MAX = 10;    // wrong codes per member per window
    public const GUESS_WINDOW = 900;
    public const IP_MAX = 40;       // wrong codes from one IP per window

    private const VERSION_KEY = 'login-limits-version';

    public static function version(): int
    {
        return (int) Cache::get(self::VERSION_KEY, 0);
    }

    public static function key(string $prefix, $id): string
    {
        $v = self::version();
        return $prefix . ($v ? 'v' . $v . ':' : '') . $id;
    }

    public static function memberId($memberId): string
    {
        return ltrim(trim((string) $memberId), '0');
    }

    public static function sendKey($memberId): string
    {
        return self::key('otp-send:', self::memberId($memberId));
    }

    public static function guessKey($memberId): string
    {
        return self::key('otp-guess:', self::memberId($memberId));
    }

    public static function ipKey($ip): string
    {
        return self::key('otp-ip:', $ip);
    }

    // Writes one line of the "who tried" list ($kind = 'code' or 'wrong'). Never allowed to break a login: if the
    // table is missing (code deployed before the migration) or the write fails, the try simply is not listed.
    public static function record($memberId, string $kind, $ip = null): void
    {
        try {
            $id = self::memberId($memberId);
            if ($id === '') {
                return;
            }
            DB::table('login_attempts')->insert(['member_id' => $id, 'kind' => $kind, 'ip' => $ip, 'created_at' => now()]);
        } catch (\Throwable $e) {
            // the list is a convenience, the limits themselves live in the cache
        }
    }

    // Everybody who tried in the last 24 hours: tries, last try, and whether they are blocked right now.
    public static function recentMembers(int $limit = 300): array
    {
        try {
            $rows = DB::table('login_attempts')
                ->where('created_at', '>=', now()->subDay())
                ->selectRaw("member_id, SUM(kind = 'code') AS codes, SUM(kind = 'wrong') AS wrong, MAX(created_at) AS last_try")
                ->groupBy('member_id')
                ->orderByDesc('last_try')
                ->limit($limit)
                ->get();
        } catch (\Throwable $e) {
            return [];
        }

        $names = DB::table('fpm_users')->whereIn('MemberId', $rows->pluck('member_id')->all())->pluck('UserFullName', 'MemberId');

        return $rows->map(function ($r) use ($names) {
            $s = self::status($r->member_id);
            return [
                'member_id' => $r->member_id,
                'name' => $names[$r->member_id] ?? null,
                'codes' => (int) $r->codes,
                'wrong' => (int) $r->wrong,
                'last_try' => $r->last_try,
                'blocked' => $s['sent_blocked'] || $s['wrong_blocked'],
                'why' => trim(($s['sent_blocked'] ? 'too many codes requested' : '') . ($s['sent_blocked'] && $s['wrong_blocked'] ? ', ' : '') . ($s['wrong_blocked'] ? 'too many wrong codes' : '')),
            ];
        })->all();
    }

    // What one member has used right now.
    public static function status($memberId): array
    {
        $sent = RateLimiter::attempts(self::sendKey($memberId));
        $wrong = RateLimiter::attempts(self::guessKey($memberId));

        return [
            'sent' => $sent,
            'sent_max' => self::SEND_MAX,
            'sent_blocked' => $sent >= self::SEND_MAX,
            'wrong' => $wrong,
            'wrong_max' => self::GUESS_MAX,
            'wrong_blocked' => $wrong >= self::GUESS_MAX,
        ];
    }

    public static function resetMember($memberId): void
    {
        RateLimiter::clear(self::sendKey($memberId));
        RateLimiter::clear(self::guessKey($memberId));
    }

    // Everybody, including the per-IP counters.
    public static function resetAll(): void
    {
        Cache::forever(self::VERSION_KEY, self::version() + 1);
    }
}
