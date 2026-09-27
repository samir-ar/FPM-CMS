<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('national_plans', function (Blueprint $table) {
            $table->date('date')->nullable()->after('title');
        });
    }

    public function down(): void
    {
        Schema::table('national_plans', function (Blueprint $table) {
            $table->dropColumn('date');
        });
    }
};
