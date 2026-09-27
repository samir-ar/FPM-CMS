<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Adds the "Dashboards" sidebar parent (grouping User Activity + Content
// Engagement) — done via raw SQL locally first, this migration replays the
// same structure so it also lands on staging/production. The sidebar has no
// sort-order column (renders `pages` in row/id order), so "Dashboards" is
// deliberately forced to id=0 to appear first — same technique used locally.
return new class extends Migration
{
    public function up(): void
    {
        // Wrapped in a transaction so a failure partway through (e.g. the
        // FK-constrained admins_pages inserts) can't leave orphaned/partial
        // rows behind — learned the hard way while writing this migration.
        DB::transaction(function () {
            $this->run();
        });
    }

    private function run(): void
    {
        // Content Engagement may already exist as a normal top-level page
        // from an earlier migration/seed on this environment — reuse it if so.
        $contentEngagementId = DB::table('pages')->where('url', 'admin.content-engagement.index')->value('id');
        if ($contentEngagementId === null) {
            $contentEngagementId = DB::table('pages')->insertGetId([
                'name' => 'Content Engagement',
                'is_parent' => 0,
                'logo' => 'fa fa-heart',
                'parent_id' => null,
                'url' => 'admin.content-engagement.index',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $userActivityId = DB::table('pages')->where('url', 'admin.user-activity.index')->value('id');
        if ($userActivityId === null) {
            $userActivityId = DB::table('pages')->insertGetId([
                'name' => 'User Activity',
                'is_parent' => 0,
                'logo' => 'fa fa-bolt',
                'parent_id' => null,
                'url' => 'admin.user-activity.index',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $dashboardsId = DB::table('pages')->where('name', 'Dashboards')->where('is_parent', 1)->value('id');
        if ($dashboardsId === null) {
            // Insert normally first, then move it to id=0 with a separate
            // UPDATE — MySQL silently discards an explicit id=0 on INSERT
            // into an auto_increment column (treats it like NULL and
            // generates a real id instead) unless NO_AUTO_VALUE_ON_ZERO is
            // set, but a plain UPDATE ... SET id=0 on an existing row isn't
            // subject to that special-case, so this two-step version is the
            // reliable way to force id=0 (needed to sort first, since the
            // sidebar has no sort-order column).
            $dashboardsId = DB::table('pages')->insertGetId([
                'name' => 'Dashboards',
                'is_parent' => 1,
                'logo' => 'fa fa-tachometer',
                'parent_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $idZeroFree = !DB::table('pages')->where('id', 0)->exists();
            if ($idZeroFree) {
                DB::statement('SET FOREIGN_KEY_CHECKS=0');
                DB::table('pages')->where('id', $dashboardsId)->update(['id' => 0]);
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
                $dashboardsId = 0;
            }
        }

        DB::table('pages')->whereIn('id', [$contentEngagementId, $userActivityId])->update(['parent_id' => $dashboardsId]);

        // Grant to every admin who already has at least one page grant —
        // i.e. everyone actually using the panel, without hardcoding ids
        // that only make sense on this specific database.
        $adminIds = DB::table('admins_pages')->distinct()->pluck('user_id');
        $now = now();
        foreach ([$dashboardsId, $userActivityId, $contentEngagementId] as $pageId) {
            foreach ($adminIds as $userId) {
                $exists = DB::table('admins_pages')->where('page_id', $pageId)->where('user_id', $userId)->exists();
                if (!$exists) {
                    DB::table('admins_pages')->insert([
                        'page_id' => $pageId,
                        'user_id' => $userId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        $ids = DB::table('pages')->whereIn('url', ['admin.content-engagement.index', 'admin.user-activity.index'])
            ->orWhere(function ($q) { $q->where('name', 'Dashboards')->where('is_parent', 1); })
            ->pluck('id');

        DB::table('admins_pages')->whereIn('page_id', $ids)->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('pages')->whereIn('id', $ids)->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
