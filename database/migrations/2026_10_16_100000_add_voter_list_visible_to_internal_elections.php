<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Independent on/off switch for whether candidates/granted viewers can see
// the district voters list right now - separate from the election's own
// is_active (published) status, e.g. to turn list visibility off once
// voting starts while leaving the election itself published. Defaults to
// true so existing elections keep their current always-visible behavior
// unchanged until an admin explicitly turns it off.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('internal_elections', function (Blueprint $table) {
            $table->boolean('voter_list_visible')->default(true)->after('requires_selfie');
        });
    }

    public function down(): void
    {
        Schema::table('internal_elections', function (Blueprint $table) {
            $table->dropColumn('voter_list_visible');
        });
    }
};
