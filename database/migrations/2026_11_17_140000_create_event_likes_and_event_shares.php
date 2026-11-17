<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Until now only NEWS could be liked or shared. The Home feed offers a like
// and a share button on events too, but the app sent an event's id to the
// NEWS endpoints (`news-like` / `news-share`), so the like was either rejected
// or - worse - attached to whichever news article happened to share that
// numeric id; the feed then reported "no like info" for events, so the like
// vanished on the next refresh. Events now have their own like table and a
// share counter.
return new class extends Migration
{
    public function up()
    {
        Schema::create('event_likes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('event_id');
            $table->timestamps();

            $table->unique(['user_id', 'event_id'], 'event_likes_user_event_unique');
            $table->index('event_id');
            $table->foreign('user_id')->references('id')->on('app_users')->cascadeOnDelete();
            $table->foreign('event_id')->references('id')->on('events')->cascadeOnDelete();
        });

        if (!Schema::hasColumn('events', 'shares')) {
            Schema::table('events', function (Blueprint $table) {
                $table->unsignedInteger('shares')->default(0);
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('event_likes');

        if (Schema::hasColumn('events', 'shares')) {
            Schema::table('events', function (Blueprint $table) {
                $table->dropColumn('shares');
            });
        }
    }
};
