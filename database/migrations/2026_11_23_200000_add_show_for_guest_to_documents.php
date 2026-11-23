<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// "Visible to guests" tick for the political statements, legislative documents, laws and archive albums.
// Everything that exists today stays visible to guests (so nothing disappears the day this ships);
// anything created from now on is members-only until the admin ticks the box.
return new class extends Migration
{
    private array $tables = ['political_work_documents', 'legislative_docs', 'laws', 'albums'];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (!Schema::hasColumn($table, 'show_for_guest')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->boolean('show_for_guest')->default(false);
                });
                DB::table($table)->update(['show_for_guest' => 1]);
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasColumn($table, 'show_for_guest')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropColumn('show_for_guest');
                });
            }
        }
    }
};
