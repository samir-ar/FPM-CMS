<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('directory_members', function (Blueprint $table) {
            $table->string('member_id')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('directory_members', function (Blueprint $table) {
            $table->dropColumn('member_id');
        });
    }
};
