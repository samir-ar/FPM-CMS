<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fpm_users', function (Blueprint $table) {
            $table->index('MemberId');
        });
    }

    public function down(): void
    {
        Schema::table('fpm_users', function (Blueprint $table) {
            $table->dropIndex(['MemberId']);
        });
    }
};
