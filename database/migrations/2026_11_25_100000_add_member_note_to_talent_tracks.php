<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// The grey line under the welcome box of the Talent form ("your name, membership number ... are added automatically")
// was written inside the app. It is now a text of each track in the CMS (empty = no line). Both tracks start with the
// sentence the app used to show, so nothing changes until the admin edits it.
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('talent_tracks', 'member_note')) {
            Schema::table('talent_tracks', function (Blueprint $table) {
                $table->text('member_note')->nullable();
            });
            DB::table('talent_tracks')->update(['member_note' => 'اسمك ورقم عضويتك والقضاء والصفة التنظيمية تُضاف تلقائياً من بياناتك.']);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('talent_tracks', 'member_note')) {
            Schema::table('talent_tracks', function (Blueprint $table) {
                $table->dropColumn('member_note');
            });
        }
    }
};
