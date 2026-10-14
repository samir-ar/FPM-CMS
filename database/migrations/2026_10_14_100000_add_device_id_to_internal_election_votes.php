<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fraud-detection signal: a device-level identifier sent by the app with
 * each vote, so an admin can spot one physical device used to cast votes
 * for several different members. Nullable - older app versions won't send
 * it, and it's informational only, never a voting gate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('internal_election_votes', function (Blueprint $table) {
            $table->string('device_id')->nullable()->after('voted_at');
        });
    }

    public function down(): void
    {
        Schema::table('internal_election_votes', function (Blueprint $table) {
            $table->dropColumn('device_id');
        });
    }
};
