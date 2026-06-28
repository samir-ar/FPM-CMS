<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateFpmUsersActionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('fpm_users_actions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('MemberId');
            $table->string('GroupName');
            $table->integer('GroupId');
            $table->integer('CanChat');
            $table->integer('CanInviteToMeeting');
            $table->integer('CanViewChat');
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
        Schema::dropIfExists('fpm_users_actions');
    }
}
