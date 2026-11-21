<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// FPM Talent admin pages, each with its OWN line on the Profile form (so one admin can be allowed just one of them):
// Tracks, Roles, FAQ, Submissions and CV Files (the private files: own permission, hidden from the sidebar because its URL
// needs a file id). They sit under the existing "Competency Platform" menu. Keyed by url, idempotent. Super Admin gets Full
// on all; every profile that already had the old Competency Platform page gets the same level on the new pages, except
// "CV Files" (personal documents) which only profiles with FULL on the old page get.
return new class extends Migration
{
    // name, url, logo, hidden, full-only
    private const PAGES = [
        ['Talent Tracks', 'admin.talent-tracks.index', 'fa fa-flag', false, false],
        ['Talent Roles', 'admin.talent-roles.index', 'fa fa-tags', false, false],
        ['Talent FAQ', 'admin.talent-faqs.index', 'fa fa-question-circle', false, false],
        ['Talent Submissions', 'admin.talent-submissions.index', 'fa fa-inbox', false, false],
        ['Talent CV Files', 'admin.talent-files.download', 'fa fa-file-pdf-o', true, true],
    ];

    public function up(): void
    {
        DB::transaction(function () {
            $oldPageId = DB::table('pages')->where('url', 'admin.competency-vacancies.index')->value('id');
            $groupId = $oldPageId ? DB::table('pages')->where('id', $oldPageId)->value('parent_id')
                : DB::table('pages')->where('name', 'Competency Platform')->where('is_parent', 1)->value('id');
            $superId = DB::table('profiles')->where('name', 'Super Admin')->value('id');

            foreach (self::PAGES as [$name, $url, $logo, $hidden, $fullOnly]) {
                $pageId = DB::table('pages')->where('url', $url)->value('id');
                if ($pageId === null) {
                    $pageId = DB::table('pages')->insertGetId([
                        'name' => $name, 'url' => $url, 'logo' => $logo, 'is_parent' => 0, 'parent_id' => $groupId,
                        'show_in_profile_form' => true, 'hide' => $hidden, 'underline' => '', 'parameters' => '',
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                } else {
                    DB::table('pages')->where('id', $pageId)->update(['show_in_profile_form' => true, 'hide' => $hidden]);
                }

                $grants = [];
                if ($oldPageId !== null) {
                    foreach (DB::table('profile_permissions')->where('page_id', $oldPageId)->get() as $row) {
                        if ($fullOnly && $row->level !== 'full') {
                            continue;
                        }
                        $grants[$row->profile_id] = $row->level;
                    }
                }
                if ($superId !== null) {
                    $grants[$superId] = 'full';
                }
                foreach ($grants as $profileId => $level) {
                    if (DB::table('profile_permissions')->where('profile_id', $profileId)->where('page_id', $pageId)->exists()) {
                        continue;
                    }
                    DB::table('profile_permissions')->insert(['profile_id' => $profileId, 'page_id' => $pageId, 'level' => $level, 'created_at' => now(), 'updated_at' => now()]);
                }
            }
        });
    }

    public function down(): void
    {
        DB::transaction(function () {
            foreach (self::PAGES as [, $url]) {
                $pageId = DB::table('pages')->where('url', $url)->value('id');
                if ($pageId !== null) {
                    DB::table('profile_permissions')->where('page_id', $pageId)->delete();
                    DB::table('pages')->where('id', $pageId)->delete();
                }
            }
        });
    }
};
