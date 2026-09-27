<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class SplitCandidateNameAndAddFpmLink extends Migration
{
    public function up()
    {
        Schema::table('internal_election_candidates', function (Blueprint $table) {
            $table->string('first_name')->nullable()->after('name');
            $table->string('father_name')->nullable()->after('first_name');
            $table->string('family_name')->nullable()->after('father_name');
            // Set when the candidate was picked from fpm_users instead of
            // entered manually — the photo is then served live from there.
            $table->string('member_id')->nullable()->after('family_name');
        });

        Schema::table('internal_election_candidates', function (Blueprint $table) {
            // No longer required — fpm-linked candidates have no local file.
            $table->string('image_name')->nullable()->change();
        });

        foreach (DB::table('internal_election_candidates')->get(['id', 'name']) as $row) {
            $parts = preg_split('/\s+/', trim($row->name));
            $first = $parts[0] ?? '';
            $family = count($parts) > 1 ? end($parts) : '';
            $father = count($parts) > 2 ? implode(' ', array_slice($parts, 1, -1)) : '';

            DB::table('internal_election_candidates')->where('id', $row->id)->update([
                'first_name' => $first,
                'father_name' => $father,
                'family_name' => $family,
            ]);
        }

        Schema::table('internal_election_candidates', function (Blueprint $table) {
            $table->dropColumn('name');
        });
    }

    public function down()
    {
        Schema::table('internal_election_candidates', function (Blueprint $table) {
            $table->string('name')->nullable()->after('id');
        });

        foreach (DB::table('internal_election_candidates')->get(['id', 'first_name', 'father_name', 'family_name']) as $row) {
            $name = trim($row->first_name . ' ' . $row->father_name . ' ' . $row->family_name);
            DB::table('internal_election_candidates')->where('id', $row->id)->update(['name' => $name]);
        }

        Schema::table('internal_election_candidates', function (Blueprint $table) {
            $table->dropColumn(['first_name', 'father_name', 'family_name', 'member_id']);
        });
    }
}
