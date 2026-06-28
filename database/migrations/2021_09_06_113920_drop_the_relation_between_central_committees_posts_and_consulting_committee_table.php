<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class DropTheRelationBetweenCentralCommitteesPostsAndConsultingCommitteeTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('central_committees', function (Blueprint $table) {
            $table->foreign("post_id")->on("central_committee_posts")->references('id')->onDelete('cascade')->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('central_committees', function (Blueprint $table) {
            //
        });
    }
}
