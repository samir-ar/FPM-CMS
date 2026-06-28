<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateFpmUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('fpm_users', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('MemberId');
            $table->text('UserFullName');
            $table->text('Role');
            $table->integer('RoleId');
            $table->integer('CanCreateMeetings');
            $table->integer('CanCreateEventRequest');
            $table->date('BirthDate');
            $table->text('PersonImage');
            $table->string('ElectionState');
            $table->integer('ElectionStateId');
            $table->integer('ElectionStateVoltersNumber');
            $table->string('Email');
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
        Schema::dropIfExists('fpm_users');
    }
}
