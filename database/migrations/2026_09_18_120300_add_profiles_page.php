<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $administratorsPageId = DB::table('pages')->where('url', 'admin.admins.index')->value('parent_id');

        $pageId = DB::table('pages')->insertGetId([
            'name' => 'Profiles',
            'url' => 'admin.profiles.index',
            'parent_id' => $administratorsPageId,
            'is_parent' => 0,
        ]);

        // The Super Admin profile was created in the previous migration
        // before this page existed — grant it access here too, so nobody
        // loses the ability to manage profiles.
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
        DB::table('pages')->where('url', 'admin.profiles.index')->delete();
    }
};
