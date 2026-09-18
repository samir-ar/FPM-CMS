<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profile_permissions', function (Blueprint $table) {
            // Named actions a profile can be granted on a page independently
            // of its base level — e.g. a "View" profile can still be given
            // the specific ability to add check-in attendance, or to export,
            // without being bumped all the way up to "Full". JSON array of
            // action keys, e.g. ["attendance.store", "attendance.export"].
            // Meaningless (and ignored) when level is 'full', since Full
            // already implies every action.
            $table->json('extra_actions')->nullable()->after('level');
        });
    }

    public function down(): void
    {
        Schema::table('profile_permissions', function (Blueprint $table) {
            $table->dropColumn('extra_actions');
        });
    }
};
