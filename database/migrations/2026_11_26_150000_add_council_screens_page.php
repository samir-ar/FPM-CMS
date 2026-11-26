<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// "Screens": one menu page that opens only the three display screens of the National Council (live attendance, live
// voting, results board). An account given just this page sees nothing else. It is a top-level menu line, so one
// permission is enough. Keyed by `url` (page ids differ per server) and safe to run twice.
return new class extends Migration
{
    private const URL = 'admin.council-screens.index';

    public function up(): void
    {
        DB::transaction(function () {
            $pageId = DB::table('pages')->where('url', self::URL)->value('id');
            if ($pageId === null) {
                $pageId = DB::table('pages')->insertGetId([
                    'name' => 'Screens', 'url' => self::URL, 'logo' => 'fa fa-television', 'is_parent' => 0, 'parent_id' => null,
                    'show_in_profile_form' => true, 'hide' => false, 'underline' => '', 'parameters' => '',
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }

            $superId = DB::table('profiles')->where('name', 'Super Admin')->value('id');
            if ($superId !== null && !DB::table('profile_permissions')->where('profile_id', $superId)->where('page_id', $pageId)->exists()) {
                DB::table('profile_permissions')->insert(['profile_id' => $superId, 'page_id' => $pageId, 'level' => 'full', 'created_at' => now(), 'updated_at' => now()]);
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
