<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class DropCandidateAndRegistererIdsFromLocalBodies extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('local_bodies', function (Blueprint $table) {
            $table->dropForeign(['registerer_id']);
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
        Schema::table('local_bodies', function (Blueprint $table) {
            //
        });
    }
}
