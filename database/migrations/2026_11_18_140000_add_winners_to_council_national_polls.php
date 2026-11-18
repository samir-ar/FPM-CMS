<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // How many winners (seats) a National Council poll elects. Optional: the results pages mark this many from the
    // top (a tie on weight is won by the older candidate). Empty = not set, only the leader is highlighted.
    public function up(): void
    {
        if (Schema::hasTable('council_national_polls') && !Schema::hasColumn('council_national_polls', 'winners')) {
            Schema::table('council_national_polls', function (Blueprint $table) {
                $table->unsignedSmallInteger('winners')->nullable()->after('closes_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('council_national_polls', 'winners')) {
            Schema::table('council_national_polls', function (Blueprint $table) {
                $table->dropColumn('winners');
            });
        }
    }
};
