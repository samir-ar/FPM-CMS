<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCouncilNationalPollQuestionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('council_national_poll_questions', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('poll_id');
            $table->foreign('poll_id')->on('council_national_polls')->references('id')->onDelete('cascade')->onUpdate('cascade');

            $table->string('question');
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
        Schema::dropIfExists('council_national_poll_questions');
    }
}
