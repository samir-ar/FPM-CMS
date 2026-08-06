<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSyndicateNumberToDirectoryMembersTable extends Migration
{
    public function up()
    {
        Schema::table('directory_members', function (Blueprint $table) {
            $table->string('syndicate_number')->nullable()->after('town');
        });
    }

    public function down()
    {
        Schema::table('directory_members', function (Blueprint $table) {
            $table->dropColumn('syndicate_number');
        });
    }
}
