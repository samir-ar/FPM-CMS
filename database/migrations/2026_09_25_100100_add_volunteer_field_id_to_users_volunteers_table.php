<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users_volunteers', function (Blueprint $table) {
            $table->unsignedBigInteger('volunteer_field_id')->nullable()->after('volunteer_id');
            $table->foreign('volunteer_field_id')->references('id')->on('volunteer_fields')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('users_volunteers', function (Blueprint $table) {
            $table->dropForeign(['volunteer_field_id']);
            $table->dropColumn('volunteer_field_id');
        });
    }
};
