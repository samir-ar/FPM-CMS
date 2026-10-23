<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Lets an admin set a custom display order for districts (election_states),
// used by the Internal Election candidates list's "group by state, then by
// age" view. Null = falls back to alphabetical (by name), so nothing breaks
// for any state the admin hasn't explicitly ordered yet.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('election_states', function (Blueprint $table) {
            $table->integer('display_order')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('election_states', function (Blueprint $table) {
            $table->dropColumn('display_order');
        });
    }
};
