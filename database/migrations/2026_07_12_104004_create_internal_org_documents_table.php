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
        Schema::create('internal_org_documents', function (Blueprint $table) {
            $table->id();
            $table->enum('tab', ['nizham_dakhili', 'tawjihat_tatbiqiya']);
            $table->string('title');
            $table->string('file_name');
            $table->integer('order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('internal_org_documents');
    }
};
