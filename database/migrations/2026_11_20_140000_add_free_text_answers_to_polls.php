<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// A poll option can let the member WRITE his own text ("Other, write your answer"): the option carries a flag, the
// member's answer carries the text (max 300 characters, enforced by the app AND the server). The text is for the CMS
// only: no API ever sends it back to a member.
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('polls_options', 'allows_text')) {
            Schema::table('polls_options', function (Blueprint $table) {
                $table->boolean('allows_text')->default(false);
            });
        }
        if (!Schema::hasColumn('users_polls', 'answer_text')) {
            Schema::table('users_polls', function (Blueprint $table) {
                $table->text('answer_text')->nullable();
            });
        }
        if (!Schema::hasColumn('guest_poll_votes', 'answer_text')) {
            Schema::table('guest_poll_votes', function (Blueprint $table) {
                $table->text('answer_text')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach ([['polls_options', 'allows_text'], ['users_polls', 'answer_text'], ['guest_poll_votes', 'answer_text']] as [$t, $c]) {
            if (Schema::hasColumn($t, $c)) {
                Schema::table($t, function (Blueprint $table) use ($c) {
                    $table->dropColumn($c);
                });
            }
        }
    }
};
