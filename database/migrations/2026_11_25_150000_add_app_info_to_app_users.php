<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The app version (build number) a member's app last reported, and - once an app release sends it - the phone.
     * Filled by the API middleware from the build_number / device_model the app already sends; nothing is
     * collected that was not already arriving.
     */
    public function up(): void
    {
        Schema::table('app_users', function (Blueprint $table) {
            if (!Schema::hasColumn('app_users', 'app_build')) {
                $table->unsignedInteger('app_build')->nullable();
            }
            if (!Schema::hasColumn('app_users', 'app_build_seen_at')) {
                $table->timestamp('app_build_seen_at')->nullable();
            }
            if (!Schema::hasColumn('app_users', 'device_model')) {
                $table->string('device_model', 191)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('app_users', function (Blueprint $table) {
            foreach (['app_build', 'app_build_seen_at', 'device_model'] as $c) {
                if (Schema::hasColumn('app_users', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};
