<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
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
