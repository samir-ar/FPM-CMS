<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddDisplayOrderToInternalElectionCandidates extends Migration
{
    public function up()
    {
        Schema::table('internal_election_candidates', function (Blueprint $table) {
            $table->unsignedInteger('display_order')->nullable()->after('member_id');
        });
    }

    public function down()
    {
        Schema::table('internal_election_candidates', function (Blueprint $table) {
            $table->dropColumn('display_order');
        });
    }
}
