<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('internal_election_votes', function (Blueprint $table) {
            $table->string('selfie_path')->nullable()->after('rank');
            $table->timestamp('voted_at')->nullable()->after('selfie_path');
        });
    }

    public function down(): void
    {
        Schema::table('internal_election_votes', function (Blueprint $table) {
            $table->dropColumn(['selfie_path', 'voted_at']);
        });
    }
};
