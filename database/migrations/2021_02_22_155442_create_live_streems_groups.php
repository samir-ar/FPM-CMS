<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateLiveStreemsGroups extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('live_streams_groups', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('live_stream_id');
            $table->foreign('live_stream_id')->references('id')->on('live_streams')->onDelete('CASCADE');

            $table->unsignedBigInteger('group_group_id');
            $table->foreign('group_group_id')->references('group_id')->on('groups')->onDelete('CASCADE');

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
        Schema::dropIfExists('live_streams_groups');
    }
}
