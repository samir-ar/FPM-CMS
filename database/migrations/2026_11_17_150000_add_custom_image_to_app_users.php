<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// A member can now replace the photo shown in the app (selfie / gallery) from
// Settings > Additional information. app_users.image is overwritten with the
// party's own photo (fpm_users.PersonImage) on EVERY login, so the member's
// own photo has to live in its own column or the next login would silently
// put the old one back. The party's photo itself (fpm_users) is never touched.
return new class extends Migration
{
    public function up()
    {
        Schema::table('app_users', function (Blueprint $table) {
            $table->longText('custom_image')->nullable();
        });
    }

    public function down()
    {
        Schema::table('app_users', function (Blueprint $table) {
            $table->dropColumn('custom_image');
        });
    }
};
