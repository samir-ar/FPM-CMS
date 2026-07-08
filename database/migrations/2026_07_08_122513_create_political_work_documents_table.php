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
        Schema::create('political_work_documents', function (Blueprint $table) {
            $table->id();
            $table->enum('category', ['takattol', 'haya_siyasiya', 'majlis_siyasi']);
            $table->string('title');
            $table->string('file_name');
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('political_work_documents');
    }
};
