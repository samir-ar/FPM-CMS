<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// A batch of admin sidebar pages were added to local's `pages` table
// manually (via the admin "Add Page" form) at various points while
// building their features, rather than through a migration — so they
// never made it to staging/production through a normal deploy, even
// though the underlying feature code did. Found when production's
// sidebar came up missing ~14 real menu items after the 2026-09-30
// production deploy. Idempotent by url, same pattern as the Dashboards
// migration, safe to run on any environment regardless of current state.
return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $this->run();
        });
    }

    private function run(): void
    {
        $topLevel = [
            ['name' => 'البيانات السياسية', 'url' => 'admin.political-work.index', 'logo' => 'fa fa-landmark'],
            ['name' => 'بلديات', 'url' => 'admin.municipality-members.index', 'logo' => 'fa fa-building'],
            ['name' => 'مخاتير', 'url' => 'admin.mukhtars.index', 'logo' => 'fa fa-user-tie'],
            ['name' => 'التنظيم الداخلي', 'url' => 'admin.internal-org.index', 'logo' => 'fa fa-sitemap'],
            ['name' => 'العمل التشريعي', 'url' => 'admin.legislative-docs.index', 'logo' => 'fa fa-gavel'],
            ['name' => 'الخطط الوطنية المقدمة', 'url' => 'admin.national-plans.index', 'logo' => 'fa fa-map'],
            ['name' => 'منصة الكفاءات', 'url' => 'admin.competency-vacancies.index', 'logo' => 'fa fa-briefcase'],
            ['name' => 'الشروط والأحكام والأسئلة الشائعة', 'url' => 'admin.competency-static.index', 'logo' => 'fa fa-question-circle'],
            ['name' => 'Check-In Events', 'url' => 'admin.checkin-events.index', 'logo' => 'fa fa-check-square'],
            ['name' => 'Social Links', 'url' => 'admin.social-links.index', 'logo' => 'fa fa-share-alt'],
            ['name' => 'Legislative Doc Categories', 'url' => 'admin.legislative-doc-categories.index', 'logo' => 'fa fa-folder'],
        ];

        $insertedIds = [];
        $now = now();

        foreach ($topLevel as $page) {
            $id = DB::table('pages')->where('url', $page['url'])->value('id');
            if ($id === null) {
                $id = DB::table('pages')->insertGetId([
                    'name' => $page['name'],
                    'is_parent' => 0,
                    'logo' => $page['logo'],
                    'parent_id' => null,
                    'url' => $page['url'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
            $insertedIds[] = $id;
        }

        // "Community" parent group + its 3 children.
        $communityId = DB::table('pages')->where('name', 'Community')->where('is_parent', 1)->value('id');
        if ($communityId === null) {
            $communityId = DB::table('pages')->insertGetId([
                'name' => 'Community',
                'is_parent' => 1,
                'logo' => 'fa fa-users',
                'parent_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
        $insertedIds[] = $communityId;

        $communityChildren = [
            ['name' => 'Business Types', 'url' => 'admin.business-types.index', 'logo' => 'fa fa-industry'],
            ['name' => 'Community Posts', 'url' => 'admin.community-posts.index', 'logo' => 'fa fa-comments'],
            ['name' => 'Directory Members', 'url' => 'admin.directory-members.index', 'logo' => 'fa fa-address-book'],
        ];

        foreach ($communityChildren as $page) {
            $id = DB::table('pages')->where('url', $page['url'])->value('id');
            if ($id === null) {
                $id = DB::table('pages')->insertGetId([
                    'name' => $page['name'],
                    'is_parent' => 0,
                    'logo' => $page['logo'],
                    'parent_id' => $communityId,
                    'url' => $page['url'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } else {
                DB::table('pages')->where('id', $id)->update(['parent_id' => $communityId]);
            }
            $insertedIds[] = $id;
        }

        // Grant to every admin who already has at least one page grant —
        // same pattern as the Dashboards migration.
        $adminIds = DB::table('admins_pages')->distinct()->pluck('user_id');
        foreach ($insertedIds as $pageId) {
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
        $urls = [
            'admin.political-work.index', 'admin.municipality-members.index', 'admin.mukhtars.index',
            'admin.internal-org.index', 'admin.legislative-docs.index', 'admin.national-plans.index',
            'admin.competency-vacancies.index', 'admin.competency-static.index', 'admin.checkin-events.index',
            'admin.social-links.index', 'admin.legislative-doc-categories.index', 'admin.business-types.index',
            'admin.community-posts.index', 'admin.directory-members.index',
        ];

        $ids = DB::table('pages')->whereIn('url', $urls)
            ->orWhere(function ($q) { $q->where('name', 'Community')->where('is_parent', 1); })
            ->pluck('id');

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('admins_pages')->whereIn('page_id', $ids)->delete();
        DB::table('pages')->whereIn('id', $ids)->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
