<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddMultiStateSupportToInternalElectionCandidates extends Migration
{
    public function up()
    {
        Schema::create('internal_election_candidate_states', function (Blueprint $table) {
            $table->unsignedBigInteger('candidate_id');
            $table->unsignedBigInteger('election_state_id');

            $table->foreign('candidate_id')->references('id')->on('internal_election_candidates')->onDelete('cascade');
            $table->foreign('election_state_id')->references('id')->on('election_states')->onDelete('cascade');

            $table->primary(['candidate_id', 'election_state_id']);
            $table->engine = 'InnoDB';
        });

        // Carry every existing candidate's single district over as their
        // first (only, so far) entry in the new many-to-many table.
        $rows = DB::table('internal_election_candidates')->get(['id', 'election_state_id']);
        foreach ($rows as $row) {
            DB::table('internal_election_candidate_states')->insert([
                'candidate_id' => $row->id,
                'election_state_id' => $row->election_state_id,
            ]);
        }

        Schema::table('internal_election_candidates', function (Blueprint $table) {
            $table->dropForeign(['election_state_id']);
            $table->dropColumn('election_state_id');
        });
    }

    public function down()
    {
        Schema::table('internal_election_candidates', function (Blueprint $table) {
            $table->unsignedBigInteger('election_state_id')->nullable();
        });

        $first = DB::table('internal_election_candidate_states')->get()->groupBy('candidate_id');
        foreach ($first as $candidateId => $states) {
            DB::table('internal_election_candidates')
                ->where('id', $candidateId)
                ->update(['election_state_id' => $states->first()->election_state_id]);
        }

        Schema::table('internal_election_candidates', function (Blueprint $table) {
            $table->foreign('election_state_id')->references('id')->on('election_states');
        });

        Schema::dropIfExists('internal_election_candidate_states');
    }
}
