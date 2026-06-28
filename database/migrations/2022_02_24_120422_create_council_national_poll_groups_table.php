<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCouncilNationalPollGroupsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('council_national_poll_groups', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('group_group_id');
            $table->foreign('group_group_id')->on('groups')->references('group_id')->onDelete('cascade')->onUpdate('cascade');

            $table->unsignedBigInteger('council_national_poll_id');
            $table->foreign('council_national_poll_id')->on('council_national_polls')->references('id')->onDelete('cascade')->onUpdate('cascade');

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
        Schema::dropIfExists('council_national_poll_groups');
    }
}
