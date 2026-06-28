<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateInternalElectionVotesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('internal_election_votes', function (Blueprint $table) {
            $table->bigIncrements('id');
            
            $table->unsignedBigInteger("user_id");
            $table->foreign('user_id')->references('id')->on("app_users")->onDelete('cascade')->onUpdate('cascade');

            $table->unsignedBigInteger("candidate_id");
            $table->foreign('candidate_id')->references('id')->on('internal_election_candidates')->onDelete('cascade')->onUpdate('cascade');

            $table->integer("rank");
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('internal_election_votes');
    }
}
