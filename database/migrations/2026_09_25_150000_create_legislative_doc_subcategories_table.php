<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legislative_doc_subcategories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('legislative_doc_categories')->cascadeOnDelete();
            $table->text('name'); // translatable json {en, ar}
            $table->integer('order')->default(0);
            $table->timestamps();
            $table->engine = 'InnoDB';
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legislative_doc_subcategories');
    }
};
