<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('competency_profiles', function (Blueprint $table) {
            $table->renameColumn('village', 'town');
            $table->string('district')->nullable()->after('user_id');
        });

        Schema::table('competency_nominations', function (Blueprint $table) {
            $table->renameColumn('village', 'town');
            $table->string('district')->nullable()->after('full_name');
        });
    }

    public function down(): void
    {
        Schema::table('competency_profiles', function (Blueprint $table) {
            $table->dropColumn('district');
            $table->renameColumn('town', 'village');
        });

        Schema::table('competency_nominations', function (Blueprint $table) {
            $table->dropColumn('district');
            $table->renameColumn('town', 'village');
        });
    }
};
