<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateUserSharesTable extends Migration
{
    public function up()
    {
        Schema::create('user_shares', function (Blueprint $table) {
            $table->engine = 'InnoDB'; // local WAMP defaults to MyISAM, which silently drops FKs
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('app_users');

            $table->unsignedBigInteger('news_id');
            $table->foreign('news_id')->references('id')->on('news');

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('user_shares');
    }
}
