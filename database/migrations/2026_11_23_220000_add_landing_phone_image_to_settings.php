<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The phone picture on the public landing page (fpmmobile.com) can be replaced from the CMS (App Versions / Settings page).
// NULL = the original built-in picture (landing/img/app.svg).
return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('settings', 'landing_phone_image')) {
            Schema::table('settings', function (Blueprint $table) {
                $table->string('landing_phone_image')->nullable();
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('settings', 'landing_phone_image')) {
            Schema::table('settings', function (Blueprint $table) {
                $table->dropColumn('landing_phone_image');
            });
        }
    }
};
