<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class DropCandidateAndRegistererIdsFromDistrictBodies extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('district_bodies', function (Blueprint $table) {
          /*   $table->dropForeign(['register_id']); */
            $table->dropColumn('register_id');

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
        Schema::table('district_bodies', function (Blueprint $table) {
            //
        });
    }
}
