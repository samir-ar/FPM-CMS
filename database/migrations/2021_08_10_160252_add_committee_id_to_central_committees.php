<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddCommitteeIdToCentralCommittees extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('central_committees', function (Blueprint $table) {
            $table->unsignedBigInteger('committee_id')->nullable();
            $table->foreign('committee_id')->references('id')->on('committees');
            
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
            $table->dropForeign("committee_id");
            $table->dropColumn("committee_id");
        });
    }
}
