<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPhoneAddressToCommunityPostsTable extends Migration
{
    public function up()
    {
        Schema::table('community_posts', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('email');
            $table->string('address')->nullable()->after('phone');
        });
    }

    public function down()
    {
        Schema::table('community_posts', function (Blueprint $table) {
            $table->dropColumn(['phone', 'address']);
        });
    }
}
