<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class DropCandidateAndRegistererIdsFromCentralCommittees extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('central_committees', function (Blueprint $table) {
    
            $table->dropColumn('registerer_id');

            $table->dropForeign(['candidate_id']);
            $table->dropColumn('candidate_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('central_committees', function (Blueprint $table) {
            //
        });
    }
}