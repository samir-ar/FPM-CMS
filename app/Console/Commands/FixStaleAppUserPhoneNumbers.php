<?php

namespace App\Console\Commands;

use App\V2\FpmUser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

// Found 2026-10-21: app_users.phone_number was only ever set once, at a
// member's very first login (RegistrationController::sendVerfication()'s
// "existing user" branch never updated it afterward - see that fix, commit
// e2e6ffc). Any member who changed their real phone number in fpm_users
// (synced from TWH) since their first login has a stale app_users.phone_number
// - and since RegistrationRepository::sendVerificationPin() sends the OTP to
// THAT column, not fpm_users.MobileNumber, every one of them is silently
// locked out: their login credentials validate fine, but the OTP goes to a
// dead number they can't receive.
//
// Dry-run by default (reports the mismatch count + a sample), --commit to
// actually fix them. Never touches fpm_users (the source of truth) - only
// brings app_users.phone_number back in sync with it.
class FixStaleAppUserPhoneNumbers extends Command
{
    protected $signature = 'app-users:fix-stale-phone-numbers {--commit : Actually apply the fix (default is dry-run)}';

    protected $description = 'Sync app_users.phone_number from fpm_users.MobileNumber wherever they have drifted apart';

    public function handle()
    {
        $commit = (bool) $this->option('commit');

        $mismatches = DB::table('app_users as au')
            ->join('fpm_users as fu', 'fu.MemberId', '=', 'au.member_id')
            ->whereRaw("TRIM(LEADING '+' FROM au.phone_number) != TRIM(LEADING '+' FROM fu.MobileNumber)")
            ->whereNull('au.deleted_at')
            ->whereNotNull('fu.MobileNumber')
            ->where('fu.MobileNumber', '!=', '')
            ->select('au.id', 'au.member_id', 'au.phone_number as old_phone', 'fu.MobileNumber as correct_phone', 'fu.UserFullName')
            ->get();

        $count = $mismatches->count();

        if ($count === 0) {
            $this->info('No stale app_users.phone_number rows found - nothing to do.');
            return 0;
        }

        $this->warn(($commit ? 'Fixing' : '[DRY RUN] Found') . " {$count} app_users row(s) with a phone_number that no longer matches fpm_users.MobileNumber:");

        foreach ($mismatches->take(20) as $row) {
            $this->line("  member_id={$row->member_id} ({$row->UserFullName}): {$row->old_phone} -> {$row->correct_phone}");
        }
        if ($count > 20) {
            $this->line('  ... and ' . ($count - 20) . ' more');
        }

        if (!$commit) {
            $this->line('');
            $this->info('Dry run only - re-run with --commit to actually apply these fixes.');
            return 0;
        }

        $updated = 0;
        foreach ($mismatches as $row) {
            $correctNormalized = ltrim($row->correct_phone, '+');
            DB::table('app_users')->where('id', $row->id)->update(['phone_number' => $correctNormalized]);
            $updated++;
        }

        $this->info("Updated {$updated} row(s).");
        return 0;
    }
}
