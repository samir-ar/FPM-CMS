<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('competency_vacancies', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('end_date');
        });
    }

    public function down(): void
    {
        Schema::table('competency_vacancies', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
