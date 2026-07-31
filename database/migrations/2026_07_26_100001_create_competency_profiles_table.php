<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competency_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->string('village')->nullable();
            $table->string('civil_record')->nullable();
            $table->string('profession')->nullable();
            $table->string('movement_relation')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('app_users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competency_profiles');
    }
};
