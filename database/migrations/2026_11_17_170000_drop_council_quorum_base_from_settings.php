<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The council quorum is now read straight from the council roster (members and
// their weights) when a meeting is created, instead of from a typed-in
// "quorum base" (755 / 105). Nothing reads these two settings any more.
return new class extends Migration
{
    public function up()
    {
        if (Schema::hasColumn('settings', 'council_quorum_total_weight')) {
            Schema::table('settings', function (Blueprint $table) {
                $table->dropColumn(['council_quorum_total_weight', 'council_quorum_member_count']);
            });
        }
    }

    public function down()
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->unsignedInteger('council_quorum_total_weight')->nullable();
            $table->unsignedInteger('council_quorum_member_count')->nullable();
        });
    }
};
