<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Pages built inside an existing page used to ride on their parent page's permission (Poll results on Polls, "who voted"
// and "reset votes" on National Council Poll, the live progress and votes report on Internal Election). Each now has its
// OWN line on the Profile form, so one admin can be allowed just one page of a group.
//
// Pages whose route needs an id (a poll id...) cannot be a sidebar link: they are marked `hide` (the sidebar and the
// "where to send this admin after login" rule already skip hidden pages) and are reached from buttons in the CMS.
// Keyed by `url` (page ids differ per environment) and idempotent. Nobody loses or gains access by accident: Super Admin
// and every profile that already had access to the parent get the SAME level on the new page (Reset votes: Full only).
return new class extends Migration
{
    // name, url, logo, parent menu name, parent page url (whose grants are copied), hidden from the sidebar, full-only
    private const PAGES = [
        ['Poll Results', 'admin.polls.results', 'fa fa-bar-chart', 'Engagement', 'admin.polls.index', true, false],
        ['Council Poll Results', 'admin.national-council-poll.results-overview', 'fa fa-trophy', 'National Council', 'admin.national-council-poll.index', false, false],
        ['Council Poll - Who Voted', 'admin.national-council-poll.progress', 'fa fa-users', 'National Council', 'admin.national-council-poll.index', true, false],
        ['Council Poll - Votes Report', 'admin.national-council-poll.votes-report', 'fa fa-file-text-o', 'National Council', 'admin.national-council-poll.index', true, false],
        ['Council Poll - Reset Votes', 'admin.national-council-poll.reset-votes', 'fa fa-undo', 'National Council', 'admin.national-council-poll.index', true, true],
        ['Internal Election - Progress', 'admin.internal-election.progress', 'fa fa-line-chart', null, 'admin.internal-election.index', true, false],
        ['Internal Election - Votes Report', 'admin.internal-election.votes-report', 'fa fa-file-text-o', null, 'admin.internal-election.index', true, false],
    ];

    public function up(): void
    {
        if (!Schema::hasColumn('pages', 'hide')) {
            Schema::table('pages', function (Blueprint $table) {
                $table->boolean('hide')->default(false);
            });
        }

        DB::transaction(function () {
            $superId = DB::table('profiles')->where('name', 'Super Admin')->value('id');

            foreach (self::PAGES as [$name, $url, $logo, $parentName, $parentUrl, $hidden, $fullOnly]) {
                $parentPageId = DB::table('pages')->where('url', $parentUrl)->value('id');
                // the menu (group) the new page sits in: the named one, or the group of the parent page
                $groupId = $parentName
                    ? DB::table('pages')->where('name', $parentName)->where('is_parent', 1)->value('id')
                    : ($parentPageId ? DB::table('pages')->where('id', $parentPageId)->value('parent_id') : null);

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

                // copy who already had the parent page, at the same level
                $grants = [];
                if ($parentPageId !== null) {
                    foreach (DB::table('profile_permissions')->where('page_id', $parentPageId)->get() as $row) {
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
                    DB::table('profile_permissions')->insert([
                        'profile_id' => $profileId, 'page_id' => $pageId, 'level' => $level,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
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
