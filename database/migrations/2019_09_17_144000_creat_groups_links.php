<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatGroupsLinks extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('groups_links', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('group_group_id');
            $table->foreign('group_group_id')->references('group_id')->on('groups');

            $table->unsignedBigInteger('link_id');
            $table->foreign('link_id')->references('id')->on('important_links');


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
        Schema::dropIfExists('groups_links');
    }
}
