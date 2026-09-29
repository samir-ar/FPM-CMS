<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Per-election voter allowlist, replacing the global Allowed_to_vote column
// (see the next migration) — mirrors council_national_poll_permissions,
// but with no weight column since internal elections are strictly
// one-member-one-vote.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('internal_election_permissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('election_id');
            $table->string('member_id');
            $table->timestamps();

            $table->foreign('election_id')->references('id')->on('internal_elections')->onDelete('cascade')->onUpdate('cascade');
            $table->unique(['election_id', 'member_id']);
            $table->engine = 'InnoDB';
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('internal_election_permissions');
    }
};
