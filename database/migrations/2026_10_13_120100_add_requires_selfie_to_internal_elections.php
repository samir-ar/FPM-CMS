<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-election toggle, set by the admin — a selfie is only ever
 * mandatory to vote when this is on. Defaults off so existing and
 * future elections aren't silently affected by this feature.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('internal_elections', function (Blueprint $table) {
            $table->boolean('requires_selfie')->default(false)->after('closes_at');
        });
    }

    public function down(): void
    {
        Schema::table('internal_elections', function (Blueprint $table) {
            $table->dropColumn('requires_selfie');
        });
    }
};
