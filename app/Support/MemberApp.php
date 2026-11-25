<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Which app version and which phone a member uses, for the CMS lists.
 *  - version: the build number the member's app last sent (app_users.app_build, kept by the API middleware).
 *  - phone: what the app reported (app_users.device_model, once an app release sends it), otherwise the phone of
 *    the member's latest council vote or user-poll vote. Blank when nothing is known yet.
 * Never throws: a list must still open if this cannot be read.
 */
class MemberApp
{
    /** 230040143 -> "4.4.24 (230040143)". The build number is the app's own convention: 4.4.N is build 230040119+N. */
    public static function versionLabel($build): string
    {
        $build = (int) $build;
        if ($build <= 0) {
            return '';
        }

        return $build >= 230040135 ? '4.4.' . ($build - 230040119) . ' (' . $build . ')' : 'build ' . $build;
    }

    /** [member_id => ['build' => int, 'version' => label, 'model' => pretty phone name]] for the members that have anything. */
    public static function forMembers(array $memberIds): array
    {
        $out = [];
        try {
            $ids = array_values(array_unique(array_filter(array_map('strval', $memberIds), fn($i) => $i !== '')));
            foreach (array_chunk($ids, 1000) as $chunk) {
                $accounts = DB::table('app_users')->whereNull('deleted_at')->whereIn('member_id', $chunk)
                    ->get(['id', 'member_id', 'app_build', 'app_build_seen_at', 'device_model'])
                    ->sortByDesc(fn($r) => (string) $r->app_build_seen_at);

                $userIds = [];
                foreach ($accounts as $a) {
                    $m = (string) $a->member_id;
                    $userIds[$m][] = $a->id;
                    $row = $out[$m] ?? ['build' => 0, 'model' => ''];
                    if (!$row['build'] && $a->app_build) {
                        $row['build'] = (int) $a->app_build;
                    }
                    if ($row['model'] === '' && (string) $a->device_model !== '') {
                        $row['model'] = (string) $a->device_model;
                    }
                    $out[$m] = $row;
                }

                $missing = array_values(array_filter($chunk, fn($m) => ($out[(string) $m]['model'] ?? '') === ''));
                if ($missing) {
                    // oldest first, so the latest vote overwrites
                    $votes = DB::table('council_national_poll_votes')->whereIn('member_id', $missing)
                        ->whereNotNull('device_model')->where('device_model', '<>', '')
                        ->orderBy('voted_at')->get(['member_id', 'device_model']);
                    foreach ($votes as $v) {
                        $m = (string) $v->member_id;
                        $out[$m] = $out[$m] ?? ['build' => 0, 'model' => ''];
                        $out[$m]['model'] = (string) $v->device_model;
                    }
                }

                $owner = [];
                foreach ($chunk as $m) {
                    if (($out[(string) $m]['model'] ?? '') === '') {
                        foreach ($userIds[(string) $m] ?? [] as $uid) {
                            $owner[$uid] = (string) $m;
                        }
                    }
                }
                if ($owner) {
                    $polls = DB::table('users_polls')->whereIn('app_user_id', array_keys($owner))
                        ->whereNotNull('device_model')->where('device_model', '<>', '')
                        ->orderBy('id')->get(['app_user_id', 'device_model']);
                    foreach ($polls as $p) {
                        $m = $owner[$p->app_user_id];
                        $out[$m]['model'] = (string) $p->device_model;
                    }
                }
            }
        } catch (Throwable $e) {
            return [];
        }

        foreach ($out as $m => $row) {
            $out[$m]['version'] = self::versionLabel($row['build']);
            $out[$m]['model'] = DeviceName::pretty($row['model']);
        }

        return $out;
    }
}
