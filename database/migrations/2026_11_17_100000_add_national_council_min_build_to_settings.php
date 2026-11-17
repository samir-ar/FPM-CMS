<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Separate from internal_election_min_build on purpose: each can be
    // raised independently once its own app release is live in both stores.
    public function up()
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->unsignedBigInteger('national_council_min_build')->nullable()->after('internal_election_min_build');
        });
    }

    public function down()
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn('national_council_min_build');
        });
    }
};
