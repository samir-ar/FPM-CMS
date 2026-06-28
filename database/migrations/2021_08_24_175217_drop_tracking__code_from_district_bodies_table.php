<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class DropTrackingCodeFromDistrictBodiesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasColumn("district_bodies", 'tracking_code')) 
        {
            Schema::table('district_bodies', function (Blueprint $table) {
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
        Schema::table('district_bodies', function (Blueprint $table) {
            //
        });
    }
}
