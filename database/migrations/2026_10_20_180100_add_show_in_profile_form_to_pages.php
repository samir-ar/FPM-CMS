<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// The Profile create/edit form only ever listed top-level pages
// (`whereNull('parent_id')`), so real, independently-enforced sub-features
// (Business Types, Event Images, Representative Positions, etc. - each
// already has its own `page-perm:<id>` gate on its routes, see the
// 2026_10_20_180000 migration + routes/web.php's pagePermByUrl() fix) had no
// way to actually be granted/denied per profile - they could only ever be
// "none" for every profile except Super Admin's blanket grant. This flag
// marks exactly which child pages should also appear as their own row in
// that form, without changing anything for the many other child pages
// (Add Memo, Edit Memo, Add News, etc.) that are deliberately NOT
// independently permissioned - those intentionally share their parent
// resource's single page-perm gate.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->boolean('show_in_profile_form')->default(false)->after('parent_id');
        });

        $urls = [
            'admin.business-types.index',
            'admin.community-posts.index',
            'admin.directory-members.index',
            'admin.eventImages.index',
            'admin.representative-positions.index',
            'admin.representatives.category.index',
            'admin.admins.index',
            'admin.profiles.index',
            'admin.legislative-doc-categories.index',
            'admin.legislative-doc-subcategories.index',
        ];

        DB::table('pages')->whereIn('url', $urls)->update(['show_in_profile_form' => true]);
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropColumn('show_in_profile_form');
        });
    }
};
