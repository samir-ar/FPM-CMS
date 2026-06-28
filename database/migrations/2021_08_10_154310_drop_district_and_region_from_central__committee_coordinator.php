<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class DropDistrictAndRegionFromCentralCommitteeCoordinator extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up() 
    {
        Schema::table('central_committee_coordinators', function (Blueprint $table) {
            $table->dropColumn('district');
            $table->dropColumn('region');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('central_committee_coordinators', function (Blueprint $table) {
            //
        });
    }

}
