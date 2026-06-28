<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddVersionsToSettingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->boolean('maintenance')->default(false);
            $table->string('android_version')->nullable();
            $table->string('ios_version')->nullable();
            $table->string('force_update_ios')->nullable();
            $table->string('force_update_android')->nullable();
            $table->string('update_title')->nullable();
            $table->text('update_message')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('settings', function (Blueprint $table) {
            //
        });
    }
}
