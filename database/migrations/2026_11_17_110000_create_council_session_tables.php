<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// National Council session: a standing roster of council members (with their
// vote weight), meetings with their own attendance (independent of polls, a
// meeting may have no poll at all), the "special members" log for people who
// have no official TWH member id, and an optional link from a poll to a
// meeting. Also registers the two new admin pages in the menu/permission
// system, resolved by `url` (never a hard-coded id - ids drift per env).
return new class extends Migration
{
    public function up()
    {
        Schema::create('council_roster_members', function (Blueprint $table) {
            $table->id();
            $table->string('member_id', 32)->unique();
            $table->unsignedInteger('weight')->default(1);
            $table->string('role')->nullable();
            $table->string('sector')->nullable();
            $table->string('district')->nullable();
            $table->string('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('council_meetings', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->date('meeting_date')->nullable();
            // Frozen when the meeting is created, so adding a member to the
            // roster later (e.g. the women's representative joining mid
            // session) never changes that meeting's quorum.
            $table->unsignedInteger('member_count')->default(0);
            $table->unsignedInteger('total_weight')->default(0);
            $table->unsignedInteger('quorum_majority')->default(0);
            $table->unsignedInteger('quorum_two_thirds')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by_admin_id')->nullable();
            $table->timestamps();
        });

        Schema::create('council_meeting_attendance', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('meeting_id');
            $table->string('member_id', 32);
            // Weight at the moment of check-in (kept even if the roster
            // weight is edited later).
            $table->unsignedInteger('weight')->default(1);
            $table->timestamp('checked_in_at')->nullable();
            $table->unsignedBigInteger('added_by_admin_id')->nullable();
            $table->unsignedBigInteger('scanned_by_app_user_id')->nullable();
            $table->timestamps();

            $table->foreign('meeting_id')->references('id')->on('council_meetings')->cascadeOnDelete();
            $table->unique(['meeting_id', 'member_id'], 'cma_meeting_member_unique');
        });

        Schema::create('special_members', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('member_id')->unique();
            $table->string('full_name');
            $table->string('phone', 32);
            $table->string('sector')->nullable();
            $table->string('district')->nullable();
            $table->string('email')->nullable();
            $table->text('note')->nullable();
            $table->unsignedBigInteger('created_by_admin_id')->nullable();
            $table->timestamps();
        });

        Schema::table('council_national_polls', function (Blueprint $table) {
            $table->unsignedBigInteger('meeting_id')->nullable()->after('is_published');
            $table->foreign('meeting_id', 'cnp_meeting_fk')->references('id')->on('council_meetings')->nullOnDelete();
        });

        $this->ensurePage('Council Meetings', 'admin.council-meetings.index');
        $this->ensurePage('Special Members', 'admin.special-members.index');
    }

    private function ensurePage(string $name, string $url): void
    {
        if (DB::table('pages')->where('url', $url)->exists()) {
            return;
        }

        $parentId = DB::table('pages')->where('url', 'admin.checkin-events.index')->value('parent_id');

        $row = [
            'name' => $name,
            'url' => $url,
            'parent_id' => $parentId,
            'is_parent' => 0,
        ];
        if (Schema::hasColumn('pages', 'show_in_profile_form')) {
            $row['show_in_profile_form'] = 1;
        }
        $pageId = DB::table('pages')->insertGetId($row);

        // Same safety net as every permission-adding migration: Super Admin
        // must not be locked out of a brand-new page.
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

    public function down()
    {
        Schema::table('council_national_polls', function (Blueprint $table) {
            $table->dropForeign('cnp_meeting_fk');
            $table->dropColumn('meeting_id');
        });
        Schema::dropIfExists('special_members');
        Schema::dropIfExists('council_meeting_attendance');
        Schema::dropIfExists('council_meetings');
        Schema::dropIfExists('council_roster_members');

        $ids = DB::table('pages')->whereIn('url', ['admin.council-meetings.index', 'admin.special-members.index'])->pluck('id');
        DB::table('profile_permissions')->whereIn('page_id', $ids)->delete();
        DB::table('pages')->whereIn('id', $ids)->delete();
    }
};
