<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // The make / model / OS version of the phone a vote was cast from (for example "Samsung SM-A525F, Android 13"),
    // sent by the app next to the random device id so the reports can say what the phone is. Optional: votes cast
    // by an older app build, or from a phone that would not tell, keep it empty.
    public function up(): void
    {
        foreach (['council_national_poll_votes', 'internal_election_votes'] as $tableName) {
            if (Schema::hasTable($tableName) && !Schema::hasColumn($tableName, 'device_model')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->string('device_model', 191)->nullable()->after('device_id');
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['council_national_poll_votes', 'internal_election_votes'] as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'device_model')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropColumn('device_model');
                });
            }
        }
    }
};
