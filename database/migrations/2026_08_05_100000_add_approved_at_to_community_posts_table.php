<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddApprovedAtToCommunityPostsTable extends Migration
{
    public function up()
    {
        Schema::table('community_posts', function (Blueprint $table) {
            $table->timestamp('approved_at')->nullable()->after('status');
        });
    }

    public function down()
    {
        Schema::table('community_posts', function (Blueprint $table) {
            $table->dropColumn('approved_at');
        });
    }
}
