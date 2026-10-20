<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Two real gaps found 2026-10-20 while fixing permission granularity:
//
// 1. The 2026_09_18_120300_add_profiles_page migration already ran (and is
//    recorded as such) on this environment, but its inserted "Profiles" page
//    row was separately deleted during unrelated local troubleshooting back
//    on 2026-09-24 (see CHANGELOG) and never came back - Laravel won't
//    re-run an already-recorded migration. Re-insert it if missing, matched
//    by url so this is a no-op anywhere it already exists correctly
//    (confirmed present on production as id 91, staging/local may differ).
//
// 2. "Legislative Doc Subcategories" never had a `pages` row at all, on any
//    environment - a genuinely new admin feature that was never added to the
//    menu/permission system. Created here for the first time everywhere.
return new class extends Migration
{
    public function up(): void
    {
        $this->ensurePage(
            name: 'Profiles',
            url: 'admin.profiles.index',
            parentUrl: 'admin.admins.index',
        );

        $this->ensurePage(
            name: 'Legislative Doc Subcategories',
            url: 'admin.legislative-doc-subcategories.index',
            parentUrl: 'admin.legislative-doc-categories.index',
        );
    }

    private function ensurePage(string $name, string $url, string $parentUrl): void
    {
        if (DB::table('pages')->where('url', $url)->exists()) {
            return;
        }

        $parentId = DB::table('pages')->where('url', $parentUrl)->value('parent_id');

        $pageId = DB::table('pages')->insertGetId([
            'name' => $name,
            'url' => $url,
            'parent_id' => $parentId,
            'is_parent' => 0,
        ]);

        // Same safety net as every other permission-adding migration: make
        // sure Super Admin (and anyone else already holding Full on the
        // parent feature) isn't suddenly blocked from a page that previously
        // had no distinct permission gate at all.
        $superAdminProfileId = DB::table('profiles')->where('name', 'Super Admin')->value('id');
        if ($superAdminProfileId) {
            DB::table('profile_permissions')->insert([
                'profile_id' => $superAdminProfileId,
                'page_id' => $pageId,
                'level' => 'full',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('pages')->where('url', 'admin.legislative-doc-subcategories.index')->delete();
        // Deliberately not deleting the Profiles page on down() - that one
        // already existed independently of this migration on some
        // environments (e.g. production), so this migration isn't its sole
        // owner there.
    }
};
