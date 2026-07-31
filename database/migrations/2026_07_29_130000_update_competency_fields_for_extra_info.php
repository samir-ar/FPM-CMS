<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('competency_profiles', function (Blueprint $table) {
            $table->dropColumn('movement_relation');
            $table->string('party_role')->nullable()->after('profession');
            $table->string('education_level')->nullable()->after('party_role');
            $table->string('specialization')->nullable()->after('education_level');
        });

        Schema::table('competency_nominations', function (Blueprint $table) {
            $table->dropColumn('movement_relation');
            $table->string('party_role')->nullable()->after('profession');
            $table->string('education_level')->nullable()->after('party_role');
            $table->string('specialization')->nullable()->after('education_level');
        });
    }

    public function down(): void
    {
        Schema::table('competency_profiles', function (Blueprint $table) {
            $table->dropColumn(['party_role', 'education_level', 'specialization']);
            $table->string('movement_relation')->nullable();
        });

        Schema::table('competency_nominations', function (Blueprint $table) {
            $table->dropColumn(['party_role', 'education_level', 'specialization']);
            $table->string('movement_relation')->nullable();
        });
    }
};
