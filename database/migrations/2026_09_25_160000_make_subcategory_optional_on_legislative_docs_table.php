<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('legislative_docs', function (Blueprint $table) {
            $table->foreignId('category_id')->after('id')
                ->constrained('legislative_doc_categories')
                ->cascadeOnDelete();
            $table->foreignId('subcategory_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('legislative_docs', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropColumn('category_id');
            $table->foreignId('subcategory_id')->nullable(false)->change();
        });
    }
};
