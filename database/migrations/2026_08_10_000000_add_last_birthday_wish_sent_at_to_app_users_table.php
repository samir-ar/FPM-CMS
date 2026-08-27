<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('app_users', function (Blueprint $table) {
            if (!Schema::hasColumn('app_users', 'last_birthday_wish_sent_at')) {
                $table->dateTime('last_birthday_wish_sent_at')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('app_users', function (Blueprint $table) {
            $table->dropColumn(['last_birthday_wish_sent_at']);
        });
    }
};
