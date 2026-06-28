<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class DropTrackingCodeFromDistrictCoordinatorTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasColumn("district_coordinators", 'tracking_code')) //check the column
    {
        Schema::table('district_coordinators', function (Blueprint $table) {
            $table->dropColumn('tracking_code');
        });
    }
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
