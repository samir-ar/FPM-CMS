<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// A small log of app-login tries so the CMS page "Login limits" can list who asked for codes / typed wrong codes.
// The counters that actually block a member live in the cache and cannot be listed; this table is only the list.
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('login_attempts')) {
            return;
        }

        Schema::create('login_attempts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('member_id', 32)->index();   // without leading zeros
            $table->string('kind', 10);                 // 'code' = asked for a code, 'wrong' = typed a wrong code
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_attempts');
    }
};
