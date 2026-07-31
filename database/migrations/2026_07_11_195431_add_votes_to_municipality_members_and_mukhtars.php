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
        Schema::table('municipality_members', function (Blueprint $table) {
            $table->unsignedInteger('votes')->default(0)->after('position');
        });

        Schema::table('mukhtars', function (Blueprint $table) {
            $table->unsignedInteger('votes')->default(0)->after('position');
        });
    }

    public function down(): void
    {
        Schema::table('municipality_members', function (Blueprint $table) {
            $table->dropColumn('votes');
        });

        Schema::table('mukhtars', function (Blueprint $table) {
            $table->dropColumn('votes');
        });
    }
};
