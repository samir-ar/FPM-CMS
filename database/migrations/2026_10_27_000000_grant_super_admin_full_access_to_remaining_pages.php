<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// The original Super Admin backfill (2026_09_18_120200) granted Full access
// to every page that existed AT THAT MOMENT - a one-time snapshot, not an
// ongoing sync. Every real admin page built since (Social Links, Legislative
// Doc Categories, Content Engagement, User Activity, Store Analytics, and
// others) never got a corresponding grant, so "Super Admin" has quietly
// stopped meaning "full access to everything" as the app grew. Found
// 2026-10-27: Super Admin had grants for only 74 of 88 pages.
return new class extends Migration
{
    public function up(): void
    {
        // Resolve by name, not a literal profile_id - cross-environment
        // id drift is a known, real risk in this project (see pagePermByUrl()
        // and the 2026-10-20 permission-system fixes).
        $profileId = DB::table('profiles')->where('name', 'Super Admin')->value('id');
        if (!$profileId) {
            return;
        }

        $alreadyGrantedPageIds = DB::table('profile_permissions')
            ->where('profile_id', $profileId)
            ->pluck('page_id');

        $missingPageIds = DB::table('pages')
            ->whereNotIn('id', $alreadyGrantedPageIds)
            ->pluck('id');

        $now = now();
        $rows = $missingPageIds->map(fn($pageId) => [
            'profile_id' => $profileId,
            'page_id' => $pageId,
            'level' => 'full',
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        if (!empty($rows)) {
            DB::table('profile_permissions')->insert($rows);
        }
    }

    public function down(): void
    {
        // Intentionally no-op - reversing this would mean guessing which
        // grants were already there vs. added by this migration, and the
        // intended end state (Super Admin really does have full access to
        // everything) is correct regardless.
    }
};
