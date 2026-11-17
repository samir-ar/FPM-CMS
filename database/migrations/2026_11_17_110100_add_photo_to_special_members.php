<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Keeps a copy of the (resized) photo with the special member, so their row in
// fpm_users can be restored from here if the TWH sync ever removes it.
return new class extends Migration
{
    public function up()
    {
        Schema::table('special_members', function ($table) {
            $table->binary('photo')->nullable();
        });
        // Schema builder's binary() is a plain BLOB (64KB); photos are resized
        // below that, but give headroom.
        DB::statement('ALTER TABLE special_members MODIFY photo MEDIUMBLOB NULL');
    }

    public function down()
    {
        Schema::table('special_members', function ($table) {
            $table->dropColumn('photo');
        });
    }
};
