<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competency_nominations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vacancy_id');
            $table->unsignedBigInteger('submitted_by_user_id');
            $table->enum('nomination_type', ['self', 'other']);
            $table->string('full_name');
            $table->string('village')->nullable();
            $table->string('civil_record')->nullable();
            $table->string('phone')->nullable();
            $table->string('profession')->nullable();
            $table->string('movement_relation')->nullable();
            $table->string('nominee_relation')->nullable();
            $table->boolean('nominee_aware')->nullable();
            $table->boolean('responsibility_confirmed')->default(false);
            $table->timestamps();

            $table->foreign('vacancy_id')->references('id')->on('competency_vacancies')->onDelete('cascade');
            $table->foreign('submitted_by_user_id')->references('id')->on('app_users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competency_nominations');
    }
};
