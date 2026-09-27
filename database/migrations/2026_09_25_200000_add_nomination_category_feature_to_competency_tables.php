<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('competency_vacancies', function (Blueprint $table) {
            // Per-vacancy toggle: when enabled, the app's nomination form (both
            // self and other) shows a required "نوع الترشيح" dropdown
            // (سياسي / تنفيذي-اداري / مناطقي) for this specific position only.
            $table->boolean('requires_nomination_category')->default(false)->after('type');
        });

        Schema::table('competency_nominations', function (Blueprint $table) {
            $table->string('nomination_category')->nullable()->after('nomination_type');
        });
    }

    public function down(): void
    {
        Schema::table('competency_vacancies', function (Blueprint $table) {
            $table->dropColumn('requires_nomination_category');
        });

        Schema::table('competency_nominations', function (Blueprint $table) {
            $table->dropColumn('nomination_category');
        });
    }
};
