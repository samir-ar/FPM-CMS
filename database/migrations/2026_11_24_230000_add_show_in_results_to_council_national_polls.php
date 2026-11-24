<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// A tick on each poll of the polls list: "Show on results page". The results board (national-council-poll-results)
// lists exactly the ticked polls, whether they are published or not. Polls that are published today are ticked, so
// the board shows the same polls the day this ships; every new poll starts NOT ticked.
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('council_national_polls', 'show_in_results')) {
            Schema::table('council_national_polls', function (Blueprint $table) {
                $table->boolean('show_in_results')->default(false);
            });
            DB::table('council_national_polls')->where('is_published', 1)->update(['show_in_results' => 1]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('council_national_polls', 'show_in_results')) {
            Schema::table('council_national_polls', function (Blueprint $table) {
                $table->dropColumn('show_in_results');
            });
        }
    }
};
