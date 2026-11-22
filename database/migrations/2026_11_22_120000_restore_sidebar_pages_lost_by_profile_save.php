<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Production 2026-11-22: the sidebar showed "Transactions" EMPTY, "Administration" without "Add Account", and
// "Party Organization" without its six body/coordinator pages.
//
// Cause: these 9 sidebar items were flagged show_in_profile_form = 0 (no line on the Profile form). Saving ANY profile
// on the Profile form deletes all of that profile's grants and re-adds only the ticked lines (ProfilesController::update),
// so every save quietly dropped the grants of pages that have no line to tick. Their routes are protected by the PARENT
// page (Transactions = 1, Party Organization = 50, Add Account = admins.index), so only the sidebar entry vanished.
//
// Fix (keyed by url, ids differ per environment; idempotent):
//   1. give each of the 9 pages its own line on the Profile form (so a save can no longer wipe it and it can be granted),
//   2. restore the grant: Super Admin = full; every profile that already has the page the route is protected by gets the
//      SAME level (children used to follow their parent). Nobody gets access to anything they could not already open.
return new class extends Migration
{
    // child url => url of the page whose grants it follows (null = Super Admin only)
    private const PAGES = [
        'admin.transactions.index' => 'ROOT:Transactions',
        'admin.bills.index' => 'ROOT:Transactions',
        'admin.add_user_form' => 'admin.admins.index',
        'admin.district-coordinator.index' => 'ROOT:Party Organization',
        'admin.district-body.index' => 'ROOT:Party Organization',
        'admin.local-body.index' => 'ROOT:Party Organization',
        'admin.central-committee-coordinator.index' => 'ROOT:Party Organization',
        'admin.central-committee.index' => 'ROOT:Party Organization',
        'admin.consulting-committee.index' => 'ROOT:Party Organization',
    ];

    public function up(): void
    {
        DB::transaction(function () {
            $superId = DB::table('profiles')->where('name', 'Super Admin')->value('id');

            foreach (self::PAGES as $url => $follows) {
                $pageId = DB::table('pages')->where('url', $url)->value('id');
                if ($pageId === null) {
                    continue; // page row not on this environment: nothing to restore
                }

                DB::table('pages')->where('id', $pageId)->update(['show_in_profile_form' => true]);

                $followId = str_starts_with($follows, 'ROOT:')
                    ? DB::table('pages')->where('name', substr($follows, 5))->where('is_parent', 1)->whereNull('parent_id')->value('id')
                    : DB::table('pages')->where('url', $follows)->value('id');

                $grants = [];
                if ($followId !== null) {
                    foreach (DB::table('profile_permissions')->where('page_id', $followId)->get() as $row) {
                        $grants[$row->profile_id] = $row->level;
                    }
                }
                if ($superId !== null) {
                    $grants[$superId] = 'full';
                }

                foreach ($grants as $profileId => $level) {
                    if (DB::table('profile_permissions')->where('profile_id', $profileId)->where('page_id', $pageId)->exists()) {
                        continue; // never overwrite a choice somebody already made
                    }
                    DB::table('profile_permissions')->insert([
                        'profile_id' => $profileId, 'page_id' => $pageId, 'level' => $level,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }
        });
    }

    public function down(): void
    {
        // Intentionally no-op: removing the grants again would hide the pages once more.
    }
};
