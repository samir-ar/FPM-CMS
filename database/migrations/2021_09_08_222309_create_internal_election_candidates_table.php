<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateInternalElectionCandidatesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('internal_election_candidates', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('image_name');
            
            $table->unsignedBigInteger('election_id');
            $table->foreign('election_id')->references('id')->on('internal_elections')->onDelete('cascade')->onUpdate('cascade');

            $table->unsignedBigInteger('election_state_id');
            $table->foreign('election_state_id')->references('id')->on('election_states');

            
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('internal_election_candidates');
    }
}
