<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Two admin pages built on top of existing pages now get their OWN line on the Profile form (Profiles > permissions), so
// the admin decides who can see each one:
//   - Login Limits      (was riding on the App Users permission)  -> under Members
//   - Home Card Icons   (new, rides on nothing)                  -> under News & Updates
// Keyed by `url` (page ids differ per environment) and idempotent. Nobody loses or gains access by accident: Super Admin and
// every profile that already had FULL control on the page the new one used to depend on get FULL on the new page.
return new class extends Migration
{
    private const PAGES = [
        ['name' => 'Login Limits', 'url' => 'admin.users.login-limits', 'logo' => 'fa fa-unlock-alt', 'parent' => 'Members', 'like' => 'admin.users.index'],
        ['name' => 'Home Card Icons', 'url' => 'admin.home-card-icons.index', 'logo' => 'fa fa-circle-o', 'parent' => 'News & Updates', 'like' => 'admin.news.index'],
    ];

    public function up(): void
    {
        DB::transaction(function () {
            foreach (self::PAGES as $def) {
                $parentId = DB::table('pages')->where('name', $def['parent'])->where('is_parent', 1)->value('id');
                $pageId = DB::table('pages')->where('url', $def['url'])->value('id');
                if ($pageId === null) {
                    $pageId = DB::table('pages')->insertGetId([
                        'name' => $def['name'],
                        'url' => $def['url'],
                        'logo' => $def['logo'],
                        'is_parent' => 0,
                        'parent_id' => $parentId,
                        'show_in_profile_form' => true,
                        'underline' => '',
                        'parameters' => '',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } else {
                    DB::table('pages')->where('id', $pageId)->update(['show_in_profile_form' => true]);
                }

                $likeId = DB::table('pages')->where('url', $def['like'])->value('id');
                $profileIds = collect();
                if ($likeId !== null) {
                    $profileIds = DB::table('profile_permissions')->where('page_id', $likeId)->where('level', 'full')->pluck('profile_id');
                }
                $superId = DB::table('profiles')->where('name', 'Super Admin')->value('id');
                if ($superId !== null) {
                    $profileIds->push($superId);
                }

                foreach ($profileIds->unique() as $profileId) {
                    if (DB::table('profile_permissions')->where('profile_id', $profileId)->where('page_id', $pageId)->exists()) {
                        continue;
                    }
                    DB::table('profile_permissions')->insert([
                        'profile_id' => $profileId,
                        'page_id' => $pageId,
                        'level' => 'full',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        });
    }

    public function down(): void
    {
        DB::transaction(function () {
            foreach (self::PAGES as $def) {
                $pageId = DB::table('pages')->where('url', $def['url'])->value('id');
                if ($pageId !== null) {
                    DB::table('profile_permissions')->where('page_id', $pageId)->delete();
                    DB::table('pages')->where('id', $pageId)->delete();
                }
            }
        });
    }
};
