<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddLocationToDirectoryMembersTable extends Migration
{
    public function up()
    {
        Schema::table('directory_members', function (Blueprint $table) {
            $table->string('governorate')->nullable()->after('specialty');
            $table->string('district')->nullable()->after('governorate');
            $table->string('town')->nullable()->after('district');
        });
    }

    public function down()
    {
        Schema::table('directory_members', function (Blueprint $table) {
            $table->dropColumn(['governorate', 'district', 'town']);
        });
    }
}
