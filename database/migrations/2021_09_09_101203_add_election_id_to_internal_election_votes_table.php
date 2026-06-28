<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddElectionIdToInternalElectionVotesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('internal_election_votes', function (Blueprint $table) {
            $table->unsignedBigInteger('internal_election_id');
            $table->foreign('internal_election_id')->on('internal_elections')->references('id')->onDelete('cascade')->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('internal_election_votes', function (Blueprint $table) {
            //
        });
    }
}
