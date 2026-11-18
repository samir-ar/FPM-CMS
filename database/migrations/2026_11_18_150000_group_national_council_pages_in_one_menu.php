<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// The three National Council admin pages (National Council Poll, Council Meetings, Special Members) used to sit
// loose inside "Engagement". They now live under one sidebar menu of their own, "National Council".
//
// Keyed by page `url` (ids differ per environment) and idempotent. Anyone who could see one of the three pages
// gets the new menu with the highest level they already had on them, so nobody loses access and nobody gains any.
return new class extends Migration
{
    private const URLS = ['admin.national-council-poll.index', 'admin.council-meetings.index', 'admin.special-members.index'];
    private const NAME = 'National Council';
    private const RANK = ['view' => 1, 'full' => 2];

    public function up(): void
    {
        DB::transaction(function () {
            $menuId = DB::table('pages')->where('name', self::NAME)->where('is_parent', 1)->whereNull('parent_id')->value('id');
            if ($menuId === null) {
                $menuId = DB::table('pages')->insertGetId([
                    'name' => self::NAME,
                    'url' => '',
                    'logo' => 'fa fa-university',
                    'is_parent' => 1,
                    'parent_id' => null,
                    'underline' => '',
                    'parameters' => '',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $childIds = DB::table('pages')->whereIn('url', self::URLS)->pluck('id');
            DB::table('pages')->whereIn('id', $childIds)->update(['parent_id' => $menuId]);

            // the menu itself needs a grant to show: give it to every profile that already has any of the three pages
            $best = [];
            foreach (DB::table('profile_permissions')->whereIn('page_id', $childIds)->get() as $row) {
                $rank = self::RANK[$row->level] ?? 0;
                if ($rank > ($best[$row->profile_id] ?? 0)) {
                    $best[$row->profile_id] = $rank;
                }
            }
            $existing = DB::table('profile_permissions')->where('page_id', $menuId)->pluck('profile_id')->all();
            foreach ($best as $profileId => $rank) {
                if (in_array($profileId, $existing)) {
                    continue;
                }
                DB::table('profile_permissions')->insert([
                    'profile_id' => $profileId,
                    'page_id' => $menuId,
                    'level' => $rank >= 2 ? 'full' : 'view',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }

    public function down(): void
    {
        DB::transaction(function () {
            $menuId = DB::table('pages')->where('name', self::NAME)->where('is_parent', 1)->whereNull('parent_id')->value('id');
            if ($menuId === null) {
                return;
            }
            $engagementId = DB::table('pages')->where('name', 'Engagement')->where('is_parent', 1)->whereNull('parent_id')->value('id');
            DB::table('pages')->where('parent_id', $menuId)->update(['parent_id' => $engagementId]);
            DB::table('profile_permissions')->where('page_id', $menuId)->delete();
            DB::table('pages')->where('id', $menuId)->delete();
        });
    }
};
