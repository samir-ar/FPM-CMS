<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateGroupsMemosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('groups_memos', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('group_group_id');
                $table->foreign('group_group_id')->references('group_id')->on('groups');

            $table->unsignedBigInteger('memo_id');
            $table->foreign('memo_id')->references('id')->on('memos');


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
        Schema::dropIfExists('groups_memos');
    }
}
