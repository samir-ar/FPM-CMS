<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Login hardening. Asking for an SMS code no longer touches the member's real login token: the new token waits in
// `pending_token` and only replaces `token` once the right code is entered before `verification_expires_at`.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_users', function (Blueprint $table) {
            $table->string('pending_token', 64)->nullable()->index();
            $table->timestamp('verification_expires_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('app_users', function (Blueprint $table) {
            $table->dropIndex(['pending_token']);
            $table->dropColumn(['pending_token', 'verification_expires_at']);
        });
    }
};
