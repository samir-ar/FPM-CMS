<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profile_permissions', function (Blueprint $table) {
            $table->engine = 'InnoDB'; // see create_profiles_table migration for why
            $table->id();
            $table->foreignId('profile_id')->constrained('profiles')->cascadeOnDelete();
            $table->foreignId('page_id')->constrained('pages')->cascadeOnDelete();
            // 'view' = read-only, 'full' = can create/edit/delete. Absence of a
            // row for a given (profile_id, page_id) pair means no access at all.
            $table->enum('level', ['view', 'full']);
            $table->timestamps();

            $table->unique(['profile_id', 'page_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_permissions');
    }
};
