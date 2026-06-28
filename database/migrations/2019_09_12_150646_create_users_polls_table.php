<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateUsersPollsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('users_polls', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('app_user_id');
            $table->foreign('app_user_id')->references('id')->on('app_users');

            $table->unsignedBigInteger('poll_id');
            $table->foreign('poll_id')->references('id')->on('polls');

            $table->unsignedBigInteger('option_id');
            $table->foreign('option_id')->references('id')->on('polls_options');

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
        Schema::dropIfExists('users_polls');
    }
}
