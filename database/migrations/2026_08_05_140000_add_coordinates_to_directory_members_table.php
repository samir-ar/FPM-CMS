<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCoordinatesToDirectoryMembersTable extends Migration
{
    public function up()
    {
        Schema::table('directory_members', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->after('syndicate_number');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
        });
    }

    public function down()
    {
        Schema::table('directory_members', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude']);
        });
    }
}
