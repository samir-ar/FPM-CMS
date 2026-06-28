<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAnswerCouncilNationalPollsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('answer_council_national_polls', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('question_id');
            $table->foreign('question_id')->on('council_national_poll_questions')->references('id')->onDelete('cascade')->onUpdate('cascade');

            $table->string('answer');

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
        Schema::dropIfExists('answer_council_national_polls');
    }
}
