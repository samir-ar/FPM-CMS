<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Lets the admin grant a specific non-candidate member the same
// "see my district's voters" view a candidate automatically gets -
// e.g. campaign/party staff who need visibility without being a
// registered candidate themselves. Per-election and per-district,
// mirroring how a candidate's own access is scoped.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('internal_election_voter_viewers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('election_id');
            $table->string('member_id');
            $table->unsignedBigInteger('election_state_id');
            $table->timestamps();

            $table->foreign('election_id')->references('id')->on('internal_elections')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('election_state_id')->references('id')->on('election_states')->onDelete('cascade')->onUpdate('cascade');
            $table->unique(['election_id', 'member_id', 'election_state_id'], 'ievv_election_member_state_unique');
            $table->engine = 'InnoDB';
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('internal_election_voter_viewers');
    }
};
