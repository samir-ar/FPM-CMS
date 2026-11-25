<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// A poll can carry its own picture, drawn under the options on the Home card. Empty = the app keeps showing the
// default poll picture (the "Poll" Home card icon).
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('polls', 'image')) {
            Schema::table('polls', function (Blueprint $table) {
                $table->string('image')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('polls', 'image')) {
            Schema::table('polls', function (Blueprint $table) {
                $table->dropColumn('image');
            });
        }
    }
};
