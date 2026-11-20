<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The CMS "Reset votes" button on a council poll (locked with an SMS code) clears every vote of that poll so it can be
// voted on again. Nothing is lost for good: before the votes are deleted each one is copied here, whole, as JSON.
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('council_poll_vote_resets')) {
            return;
        }

        Schema::create('council_poll_vote_resets', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('poll_id')->index();
            $table->unsignedBigInteger('vote_id')->nullable();   // id the vote had in council_national_poll_votes
            $table->string('member_id', 32)->nullable()->index();
            $table->longText('vote');                             // the whole vote row as JSON
            $table->unsignedBigInteger('reset_by')->nullable();   // admin id
            $table->string('reset_ip', 45)->nullable();
            $table->timestamp('reset_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('council_poll_vote_resets');
    }
};
