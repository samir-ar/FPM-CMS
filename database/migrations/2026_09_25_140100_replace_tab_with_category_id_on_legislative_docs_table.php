<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // This table predates the InnoDB-by-default convention (WAMP defaults to
        // MyISAM locally), and MyISAM can't hold a foreign key constraint.
        DB::statement('ALTER TABLE legislative_docs ENGINE = InnoDB');

        Schema::table('legislative_docs', function (Blueprint $table) {
            $table->dropColumn('tab');
            $table->foreignId('category_id')->after('id')
                ->constrained('legislative_doc_categories')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('legislative_docs', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropColumn('category_id');
            $table->string('tab')->nullable();
        });
    }
};
