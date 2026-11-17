<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// The National Council quorum is always based on 755 total weight / 105
// members (the council's official figure, which includes the not-yet-elected
// women's representative seat), regardless of how many roster rows exist at
// the moment. Stored here so it can be edited if the official figure changes;
// when NULL a meeting falls back to the live roster totals.
return new class extends Migration
{
    public function up()
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->unsignedInteger('council_quorum_total_weight')->nullable();
            $table->unsignedInteger('council_quorum_member_count')->nullable();
        });

        DB::table('settings')->update([
            'council_quorum_total_weight' => 755,
            'council_quorum_member_count' => 105,
        ]);
    }

    public function down()
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn(['council_quorum_total_weight', 'council_quorum_member_count']);
        });
    }
};
