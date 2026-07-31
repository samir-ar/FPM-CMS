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
        Schema::create('mukhtars', function (Blueprint $table) {
            $table->id();
            $table->string('qada');
            $table->string('village_name');
            $table->string('neighborhood')->nullable();
            $table->string('full_name');
            $table->enum('position', ['مختار', 'عضو اختياري'])->default('مختار');
            $table->string('phone')->nullable();
            $table->boolean('is_mountasib')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index('qada');
            $table->index('village_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mukhtars');
    }
};
