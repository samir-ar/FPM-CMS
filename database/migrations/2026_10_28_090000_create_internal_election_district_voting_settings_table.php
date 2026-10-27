<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Per-election, per-district toggle for whether real voting happens
// there. Off = the district's outcome is already agreed/uncontested
// (1, 2, or 3 candidates with no real contest, or an agreed رديف among
// 4) - no ballot is shown to that district's voters, and the Results
// report ranks by the candidates' admin-set display_order instead of
// vote count. On (the default) is today's normal contested-race
// behavior, unchanged. Absence of a row for a given (election_id,
// election_state_id) pair means voting is on (the common case).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('internal_election_district_voting_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('election_id');
            $table->unsignedBigInteger('election_state_id');
            $table->boolean('voting_enabled')->default(true);
            $table->timestamps();

            // Explicit short names - the auto-generated ones exceed MySQL's
            // 64-char identifier limit on this long table name (confirmed:
            // migration failed with "Identifier name ... is too long" using
            // the default names).
            $table->foreign('election_id', 'ievs_election_id_fk')->references('id')->on('internal_elections')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('election_state_id', 'ievs_election_state_id_fk')->references('id')->on('election_states')->onDelete('cascade')->onUpdate('cascade');
            $table->unique(['election_id', 'election_state_id'], 'ievs_election_state_unique');
            $table->engine = 'InnoDB';
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('internal_election_district_voting_settings');
    }
};
