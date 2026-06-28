<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCouncilNationalPollVotesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('council_national_poll_votes', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->on('app_users')->references('id')->onDelete('cascade')->onUpdate('cascade');

            $table->unsignedBigInteger('poll_id');
            $table->foreign('poll_id')->on('council_national_polls')->references('id')->onDelete('cascade')->onUpdate('cascade');

            $table->unsignedBigInteger('question_id');
            $table->foreign('question_id')->on('council_national_poll_questions')->references('id')->onDelete('cascade')->onUpdate('cascade');

            $table->unsignedBigInteger('answer_id');
            $table->foreign('answer_id')->on('answer_council_national_polls')->references('id')->onDelete('cascade')->onUpdate('cascade');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('council_national_poll_votes');
    }
}
