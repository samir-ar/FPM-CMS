<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Which phone a vote / answer came from, for every kind of vote:
    //  - device_id     the random id the app makes once per installation (changes on reinstall)
    //  - device_model  make / model / OS version ("Samsung SM-A525F, Android 13")
    //  - hardware_id   the phone's own id as the operating system gives it to this app (Android: ANDROID_ID,
    //                  iPhone: identifierForVendor). It survives an app reinstall, so one phone cannot hide
    //                  behind a fresh device_id.
    // All optional: votes cast by older app builds keep them empty.
    private array $columns = [
        'council_national_poll_votes' => ['hardware_id'],
        'internal_election_votes' => ['hardware_id'],
        'users_polls' => ['device_id', 'device_model', 'hardware_id'],
        'guest_poll_votes' => ['device_model', 'hardware_id'],
    ];

    public function up(): void
    {
        foreach ($this->columns as $tableName => $names) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }
            foreach ($names as $name) {
                if (!Schema::hasColumn($tableName, $name)) {
                    Schema::table($tableName, function (Blueprint $table) use ($name) {
                        $table->string($name, 191)->nullable();
                    });
                }
            }
        }
    }

    public function down(): void
    {
        foreach ($this->columns as $tableName => $names) {
            foreach ($names as $name) {
                if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, $name)) {
                    Schema::table($tableName, function (Blueprint $table) use ($name) {
                        $table->dropColumn($name);
                    });
                }
            }
        }
    }
};
