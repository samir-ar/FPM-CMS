<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// FPM Talent: who gets an e-mail when someone sends a form on a track (edited on the Talent Tracks page). Empty = no e-mail.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('talent_tracks', function (Blueprint $t) {
            $t->text('notify_emails')->nullable()->after('closed_message');
        });
    }

    public function down(): void
    {
        Schema::table('talent_tracks', function (Blueprint $t) {
            $t->dropColumn('notify_emails');
        });
    }
};
