<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class MakeUserIdNullableCouncilNationalPollPermissions extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('council_national_poll_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('poll_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('council_national_poll_permissions', function (Blueprint $table) {
            //
        });
    }
}
