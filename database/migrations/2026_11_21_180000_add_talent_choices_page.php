<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// The CMS page "Talent Choices" (the drop-down lists of the FPM Talent forms) with its OWN Profile-form line, under the
// same "Competency Platform" menu as the other Talent pages. Keyed by url, idempotent. Super Admin gets Full; every
// profile that already had the Talent Roles page gets the same level.
return new class extends Migration
{
    private const URL = 'admin.talent-choices.index';

    public function up(): void
    {
        DB::transaction(function () {
            $rolesPageId = DB::table('pages')->where('url', 'admin.talent-roles.index')->value('id');
            $groupId = $rolesPageId ? DB::table('pages')->where('id', $rolesPageId)->value('parent_id')
                : DB::table('pages')->where('name', 'Competency Platform')->where('is_parent', 1)->value('id');

            $pageId = DB::table('pages')->where('url', self::URL)->value('id');
            if ($pageId === null) {
                $pageId = DB::table('pages')->insertGetId([
                    'name' => 'Talent Choices', 'url' => self::URL, 'logo' => 'fa fa-list-ul', 'is_parent' => 0, 'parent_id' => $groupId,
                    'show_in_profile_form' => true, 'hide' => false, 'underline' => '', 'parameters' => '',
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            } else {
                DB::table('pages')->where('id', $pageId)->update(['show_in_profile_form' => true, 'hide' => false]);
            }

            $grants = [];
            if ($rolesPageId !== null) {
                foreach (DB::table('profile_permissions')->where('page_id', $rolesPageId)->get() as $row) {
                    $grants[$row->profile_id] = $row->level;
                }
            }
            $superId = DB::table('profiles')->where('name', 'Super Admin')->value('id');
            if ($superId !== null) {
                $grants[$superId] = 'full';
            }
            foreach ($grants as $profileId => $level) {
                if (DB::table('profile_permissions')->where('profile_id', $profileId)->where('page_id', $pageId)->exists()) {
                    continue;
                }
                DB::table('profile_permissions')->insert(['profile_id' => $profileId, 'page_id' => $pageId, 'level' => $level, 'created_at' => now(), 'updated_at' => now()]);
            }
        });
    }

    public function down(): void
    {
        DB::transaction(function () {
            $pageId = DB::table('pages')->where('url', self::URL)->value('id');
            if ($pageId !== null) {
                DB::table('profile_permissions')->where('page_id', $pageId)->delete();
                DB::table('pages')->where('id', $pageId)->delete();
            }
        });
    }
};
