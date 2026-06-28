<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class LinkDistrictCoordinatorToUserApp extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('district_coordinators', function (Blueprint $table) {
            $table->unsignedBigInteger('candidate_id')->nullable();
            
        });
        
        Schema::table('district_coordinators', function($table) {
            $table->foreign('candidate_id')->references('id')->on('app_users')->onUpdate('cascade')->onDelete('cascade');
        });
     
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('district_coordinators', function (Blueprint $table) {
            //
        });
    }
}
