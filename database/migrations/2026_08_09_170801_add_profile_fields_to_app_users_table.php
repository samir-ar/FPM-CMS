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
            $table->string('district')->nullable();
            $table->string('town')->nullable();
            $table->string('sect')->nullable();
            $table->string('sect_number')->nullable();
            if (!Schema::hasColumn('app_users', 'date_of_birth')) {
                $table->date('date_of_birth')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('app_users', function (Blueprint $table) {
            $table->dropColumn(['district', 'town', 'sect', 'sect_number', 'date_of_birth']);
        });
    }
};
