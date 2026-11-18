<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // When a National Council poll closes (optional). From that moment the poll no longer appears in the app and
    // nobody can open or vote in it; in the CMS it stays, with its voters, votes and results.
    public function up(): void
    {
        if (Schema::hasTable('council_national_polls') && !Schema::hasColumn('council_national_polls', 'closes_at')) {
            Schema::table('council_national_polls', function (Blueprint $table) {
                $table->dateTime('closes_at')->nullable()->after('is_published');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('council_national_polls', 'closes_at')) {
            Schema::table('council_national_polls', function (Blueprint $table) {
                $table->dropColumn('closes_at');
            });
        }
    }
};
