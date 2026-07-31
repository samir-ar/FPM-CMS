<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('competency_nominations', function (Blueprint $table) {
            $table->text('nomination_reason')->nullable()->after('nominee_relation');
        });
    }

    public function down(): void
    {
        Schema::table('competency_nominations', function (Blueprint $table) {
            $table->dropColumn('nomination_reason');
        });
    }
};
