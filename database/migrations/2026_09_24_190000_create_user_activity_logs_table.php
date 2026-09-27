<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per app_user per calendar day (Beirut time) they were active.
     * Written via insertOrIgnore so concurrent requests never race, and so
     * only the first authenticated action of the day actually costs a write.
     */
    public function up(): void
    {
        Schema::create('user_activity_logs', function (Blueprint $table) {
            $table->engine = 'InnoDB'; // local WAMP defaults to MyISAM, which silently drops the FK below
            $table->id();
            $table->unsignedBigInteger('app_user_id');
            $table->date('activity_date');
            $table->timestamp('created_at')->nullable();

            $table->unique(['app_user_id', 'activity_date']);
            $table->index('activity_date');

            $table->foreign('app_user_id')->references('id')->on('app_users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_activity_logs');
    }
};
