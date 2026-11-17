<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // A poll option can now be a real member picked from the member list (like the Internal Election's
    // candidates): the option text is his name and member_id records who he is. Options typed by hand
    // keep member_id empty, so everything that exists keeps working.
    public function up(): void
    {
        Schema::table('answer_council_national_polls', function (Blueprint $table) {
            $table->string('member_id', 32)->nullable()->after('answer');
        });
    }

    public function down(): void
    {
        Schema::table('answer_council_national_polls', function (Blueprint $table) {
            $table->dropColumn('member_id');
        });
    }
};
