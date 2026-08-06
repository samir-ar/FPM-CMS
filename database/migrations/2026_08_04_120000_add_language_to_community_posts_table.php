<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddLanguageToCommunityPostsTable extends Migration
{
    public function up()
    {
        Schema::table('community_posts', function (Blueprint $table) {
            $table->enum('language', ['ar', 'en'])->default('ar')->after('business_type_id');
        });
    }

    public function down()
    {
        Schema::table('community_posts', function (Blueprint $table) {
            $table->dropColumn('language');
        });
    }
}
