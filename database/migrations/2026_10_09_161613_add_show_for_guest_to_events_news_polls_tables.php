<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * show_for_guest existed on local/some environments but was never
 * captured as a real migration - production's `events` table is missing
 * it entirely, causing a hard SQL error (1054: Unknown column) for any
 * guest viewing Events. `news` and `polls` use the same column for the
 * same guest-visibility purpose, so all three are checked here in case
 * any of them are also missing it on a given environment.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['events', 'news', 'polls'] as $table) {
            if (!Schema::hasColumn($table, 'show_for_guest')) {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->boolean('show_for_guest')->default(false);
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['events', 'news', 'polls'] as $table) {
            if (Schema::hasColumn($table, 'show_for_guest')) {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->dropColumn('show_for_guest');
                });
            }
        }
    }
};
