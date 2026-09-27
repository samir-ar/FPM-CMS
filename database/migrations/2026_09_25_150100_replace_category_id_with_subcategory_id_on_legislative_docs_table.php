<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('legislative_docs', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropColumn('category_id');
            $table->foreignId('subcategory_id')->after('id')
                ->constrained('legislative_doc_subcategories')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('legislative_docs', function (Blueprint $table) {
            $table->dropForeign(['subcategory_id']);
            $table->dropColumn('subcategory_id');
            $table->foreignId('category_id')->after('id')
                ->constrained('legislative_doc_categories')
                ->cascadeOnDelete();
        });
    }
};
