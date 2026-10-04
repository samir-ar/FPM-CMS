<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Reorganizes the admin sidebar from ~30 flat top-level items into 14
// grouped categories (News & Updates, Static Pages, FAQ, Engagement,
// Members, Political Work, Competency Platform are new; Party
// Organization/Administration are renames of the existing Tracking
// Module/Administrators categories with a few more items folded in).
// Also adds the Store Analytics dashboard page under Dashboards.
//
// Entirely idempotent and keyed by page `url` (stable across
// environments) rather than `id` (which can differ per environment) —
// same pattern as the Dashboards/backfill migrations. Safe to run on an
// environment that already has some or all of this state (e.g. local,
// where this was originally built via ad-hoc tinker commands before
// being captured here as a migration).
return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $this->run();
        });
    }

    private function findOrCreateCategory(string $name, string $logo): int
    {
        $id = DB::table('pages')->where('name', $name)->where('is_parent', 1)->whereNull('parent_id')->value('id');
        if ($id !== null) {
            return $id;
        }

        return DB::table('pages')->insertGetId([
            'name' => $name,
            'url' => '',
            'logo' => $logo,
            'is_parent' => 1,
            'parent_id' => null,
            'underline' => '',
            'parameters' => '',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function reparentByUrl(array $urls, int $categoryId): void
    {
        DB::table('pages')->whereIn('url', $urls)->update(['parent_id' => $categoryId]);
    }

    private function grantCategoryToAdminsWithAnyChild(int $categoryId): void
    {
        $childIds = DB::table('pages')->where('parent_id', $categoryId)->pluck('id');
        if ($childIds->isEmpty()) {
            return;
        }

        $userIds = DB::table('admins_pages')->whereIn('page_id', $childIds)->distinct()->pluck('user_id');
        $alreadyGranted = DB::table('admins_pages')->where('page_id', $categoryId)->pluck('user_id');

        $now = now();
        foreach ($userIds as $userId) {
            if (!$alreadyGranted->contains($userId)) {
                DB::table('admins_pages')->insert([
                    'page_id' => $categoryId,
                    'user_id' => $userId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    private function run(): void
    {
        $categories = [
            'News & Updates' => ['logo' => 'fa fa-newspaper-o', 'urls' => [
                'admin.news.index', 'admin.memos.index', 'admin.events.index', 'admin.liveStream.index',
            ]],
            'Static Pages' => ['logo' => 'fa fa-file-text', 'urls' => [
                'admin.webviews.index', 'admin.placeholders.index', 'admin.laws.index',
                'admin.achievements.index', 'admin.biography.index', 'admin.internal-processes.index',
                'admin.aboutUs.form',
            ]],
            'FAQ' => ['logo' => 'fa fa-question-circle', 'urls' => [
                'admin.faqs.index', 'admin.faqsCategories.index',
            ]],
            'Engagement' => ['logo' => 'fa fa-comments', 'urls' => [
                'admin.polls.index', 'admin.internal-election.index', 'admin.national-council-poll.index',
                'admin.volunteers.index', 'admin.talkToUs.index', 'admin.checkin-events.index',
                'admin.donation.update.form',
            ]],
            'Members' => ['logo' => 'fa fa-id-card', 'urls' => [
                'admin.users.index', 'admin.groups.index', 'admin.bulkpushnotification.index',
            ]],
            'Political Work' => ['logo' => 'fa fa-balance-scale', 'urls' => [
                'admin.legislative-docs.index', 'admin.legislative-doc-categories.index',
                'admin.national-plans.index', 'admin.political-work.index',
            ]],
            'Competency Platform' => ['logo' => 'fa fa-graduation-cap', 'urls' => [
                'admin.competency-vacancies.index', 'admin.competency-static.index',
            ]],
        ];

        $categoryIds = [];
        foreach ($categories as $name => $data) {
            $id = $this->findOrCreateCategory($name, $data['logo']);
            $this->reparentByUrl($data['urls'], $id);
            $categoryIds[] = $id;
        }

        // Party Organization (rename of Tracking Module, if not already renamed).
        $partyOrgId = DB::table('pages')->where('name', 'Party Organization')->where('is_parent', 1)->value('id');
        if ($partyOrgId === null) {
            $partyOrgId = DB::table('pages')->where('name', 'Tracking Module')->where('is_parent', 1)->value('id');
            if ($partyOrgId !== null) {
                DB::table('pages')->where('id', $partyOrgId)->update(['name' => 'Party Organization', 'logo' => 'fa fa-sitemap']);
            }
        }
        if ($partyOrgId !== null) {
            $this->reparentByUrl(['admin.internal-org.index', 'admin.municipality-members.index', 'admin.mukhtars.index'], $partyOrgId);
            $categoryIds[] = $partyOrgId;
        }

        // Administration (rename of Administrators, if not already renamed).
        $adminCatId = DB::table('pages')->where('name', 'Administration')->where('is_parent', 1)->value('id');
        if ($adminCatId === null) {
            $adminCatId = DB::table('pages')->where('name', 'Administrators')->where('is_parent', 1)->value('id');
            if ($adminCatId !== null) {
                DB::table('pages')->where('id', $adminCatId)->update(['name' => 'Administration']);
            }
        }
        if ($adminCatId !== null) {
            $this->reparentByUrl(['admin.app-versions.edit', 'admin.social-links.index'], $adminCatId);
            $categoryIds[] = $adminCatId;
        }

        // Fold "Page Contents" into "Static Pages" (if "Page Contents" still exists).
        $staticPagesId = DB::table('pages')->where('name', 'Static Pages')->where('is_parent', 1)->value('id');
        $pageContentsId = DB::table('pages')->where('name', 'Page Contents')->where('is_parent', 1)->value('id');
        if ($staticPagesId !== null && $pageContentsId !== null) {
            DB::table('pages')->where('parent_id', $pageContentsId)->update(['parent_id' => $staticPagesId]);

            $adminsWithPageContents = DB::table('admins_pages')->where('page_id', $pageContentsId)->pluck('user_id');
            $alreadyOnStatic = DB::table('admins_pages')->where('page_id', $staticPagesId)->pluck('user_id');
            $now = now();
            foreach ($adminsWithPageContents as $userId) {
                if (!$alreadyOnStatic->contains($userId)) {
                    DB::table('admins_pages')->insert(['page_id' => $staticPagesId, 'user_id' => $userId, 'created_at' => $now, 'updated_at' => $now]);
                }
            }

            DB::table('admins_pages')->where('page_id', $pageContentsId)->delete();
            DB::table('pages')->where('id', $pageContentsId)->delete();
        }

        // Store Analytics dashboard page, under Dashboards.
        $dashboardsId = DB::table('pages')->where('name', 'Dashboards')->where('is_parent', 1)->value('id');
        if ($dashboardsId !== null) {
            $storeAnalyticsExists = DB::table('pages')->where('url', 'admin.store-analytics.index')->exists();
            if (!$storeAnalyticsExists) {
                $id = DB::table('pages')->insertGetId([
                    'name' => 'Store Analytics',
                    'url' => 'admin.store-analytics.index',
                    'logo' => 'fa fa-bar-chart',
                    'is_parent' => 0,
                    'parent_id' => $dashboardsId,
                    'underline' => '',
                    'parameters' => '',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $categoryIds[] = $id;
            }
        }

        foreach ($categoryIds as $id) {
            $this->grantCategoryToAdminsWithAnyChild($id);
        }

        // Dashboards itself (and Store Analytics specifically) should also
        // reach any admin who already has another Dashboards child (e.g.
        // User Activity), even though Dashboards isn't a "new" category.
        if ($dashboardsId !== null) {
            $this->grantCategoryToAdminsWithAnyChild($dashboardsId);
        }
    }

    public function down(): void
    {
        // Schema-only revert: un-parent everything back to flat top-level
        // and drop the categories this migration created. Doesn't attempt
        // to restore the exact prior names/ids — acceptable since this is
        // a presentation-layer reorganization, not data loss.
        $categoryNames = [
            'News & Updates', 'Static Pages', 'FAQ', 'Engagement', 'Members',
            'Political Work', 'Competency Platform',
        ];

        $categoryIds = DB::table('pages')->whereIn('name', $categoryNames)->where('is_parent', 1)->pluck('id');

        DB::table('pages')->whereIn('parent_id', $categoryIds)->update(['parent_id' => null]);

        DB::table('admins_pages')->whereIn('page_id', $categoryIds)->delete();
        DB::table('pages')->whereIn('id', $categoryIds)->delete();

        $partyOrgId = DB::table('pages')->where('name', 'Party Organization')->where('is_parent', 1)->value('id');
        if ($partyOrgId !== null) {
            DB::table('pages')->where('id', $partyOrgId)->update(['name' => 'Tracking Module', 'logo' => 'fa fa-eye']);
        }

        $adminCatId = DB::table('pages')->where('name', 'Administration')->where('is_parent', 1)->value('id');
        if ($adminCatId !== null) {
            DB::table('pages')->where('id', $adminCatId)->update(['name' => 'Administrators']);
            DB::table('pages')->where('parent_id', $adminCatId)
                ->whereIn('url', ['admin.app-versions.edit', 'admin.social-links.index'])
                ->update(['parent_id' => null]);
        }

        $storeAnalyticsId = DB::table('pages')->where('url', 'admin.store-analytics.index')->value('id');
        if ($storeAnalyticsId !== null) {
            DB::table('admins_pages')->where('page_id', $storeAnalyticsId)->delete();
            DB::table('pages')->where('id', $storeAnalyticsId)->delete();
        }
    }
};
