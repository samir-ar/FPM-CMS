<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('profile_id')->nullable()->after('id')->constrained('profiles')->nullOnDelete();
        });

        // Safety net: real enforcement is going live with this migration, so
        // every admin that already exists must not lose access. Create a
        // "Super Admin" profile with Full access to every existing page and
        // assign every current admin to it. New admins created after this
        // point start with NO profile (fully blocked) until deliberately
        // assigned one — secure-by-default, but nobody already using the
        // system gets locked out today.
        $profileId = DB::table('profiles')->insertGetId([
            'name' => 'Super Admin',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $pageIds = DB::table('pages')->pluck('id');
        $now = now();
        $rows = $pageIds->map(fn($pageId) => [
            'profile_id' => $profileId,
            'page_id' => $pageId,
            'level' => 'full',
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        if (!empty($rows)) {
            DB::table('profile_permissions')->insert($rows);
        }

        DB::table('users')->update(['profile_id' => $profileId]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['profile_id']);
            $table->dropColumn('profile_id');
        });
    }
};
