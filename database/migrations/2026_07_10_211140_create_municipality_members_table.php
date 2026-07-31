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
        Schema::create('municipality_members', function (Blueprint $table) {
            $table->id();
            $table->string('qada');                          // قضاء e.g. قضاء المتن
            $table->string('municipality_name');             // بلدية group name
            $table->string('village_name')->nullable();      // sub-village within municipality
            $table->string('full_name');                     // elected member full name
            $table->enum('position', ['رئيس', 'نائب رئيس', 'عضو'])->default('عضو');
            $table->string('phone')->nullable();             // stored, not shown in app UI
            $table->boolean('is_mountasib')->default(false); // true = show orange in app
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index('qada');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('municipality_members');
    }
};
