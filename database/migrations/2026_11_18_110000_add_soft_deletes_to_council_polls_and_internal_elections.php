<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // "Delete" in the CMS only MARKS a council poll / an Internal Election as deleted (deleted_at) and hides it
    // there and in the app. Nothing is removed from the database: the poll, its questions, voters and every vote
    // stay, and a mark can be cleared again. (Regular polls already worked like this.)
    public function up(): void
    {
        foreach (['council_national_polls', 'internal_elections'] as $tableName) {
            if (Schema::hasTable($tableName) && !Schema::hasColumn($tableName, 'deleted_at')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->softDeletes();
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['council_national_polls', 'internal_elections'] as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'deleted_at')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropSoftDeletes();
                });
            }
        }
    }
};
