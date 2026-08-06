<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCountryToDirectoryMembersTable extends Migration
{
    public function up()
    {
        Schema::table('directory_members', function (Blueprint $table) {
            $table->string('country')->nullable()->after('specialty');
        });
    }

    public function down()
    {
        Schema::table('directory_members', function (Blueprint $table) {
            $table->dropColumn('country');
        });
    }
}
