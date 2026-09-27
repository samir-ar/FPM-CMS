<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateGuestPollVotesTable extends Migration
{
    public function up()
    {
        Schema::create('guest_poll_votes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('device_id');

            $table->unsignedBigInteger('poll_id');
            $table->foreign('poll_id')->references('id')->on('polls')->onDelete('cascade');

            $table->unsignedBigInteger('option_id');
            $table->foreign('option_id')->references('id')->on('polls_options')->onDelete('cascade');

            $table->timestamps();

            // One vote per device per poll — re-voting updates this row
            // instead of creating a second one, matching how a member
            // changing their answer detaches+reattaches their own vote.
            $table->unique(['device_id', 'poll_id']);

            $table->engine = 'InnoDB';
        });
    }

    public function down()
    {
        Schema::dropIfExists('guest_poll_votes');
    }
}
