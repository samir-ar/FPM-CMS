<?php

namespace App\Console\Commands;

use App\V2\AppUser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Cleans up the legacy app_users duplicates left behind by a bug fixed on
 * 2026-08-10 (soft-deleted rows were invisible to login lookup, so a phone-
 * format mismatch would spawn a new row instead of matching the existing
 * one). That bug is fixed — this only cleans up rows already created before
 * the fix. No new duplicates should be forming.
 *
 * For each member_id with more than one non-deleted row:
 *   1. Pick a "canonical" row: prefer verified=1, then prefer a non-empty
 *      player_id (push token), tie-break by most recently updated.
 *   2. Merge any non-null field from the losing rows into the canonical row
 *      wherever the canonical row's own value is null/empty (defensive —
 *      investigation on 2026-09-18 found essentially no differing data in
 *      practice, but this keeps the command correct even if that changes).
 *   3. can_scan_checkin is OR-merged regardless of which row wins (so a
 *      real scan-access grant on a losing row is never silently dropped).
 *   4. SOFT-delete (not hard-delete) every non-canonical row — fully
 *      reversible; nothing is ever actually removed from the database.
 *
 * Defaults to a dry run (reports what it *would* do). Pass --commit to
 * actually write anything.
 */
class DedupeAppUsers extends Command
{
    protected $signature = 'app-users:dedupe
        {--commit : Actually soft-delete losing rows and merge fields. Without this, only reports what would happen.}
        {--limit= : Only process this many duplicate groups (for a small first test).}';

    protected $description = 'Merge legacy duplicate app_users rows per member_id (soft-delete only, dry-run by default)';

    // Fields worth merging from a losing row into the canonical one when
    // the canonical row's own value is empty. Deliberately excludes id,
    // member_id, created_at/updated_at/deleted_at (managed separately) and
    // can_scan_checkin/verified (handled with their own explicit rules).
    private const MERGEABLE_FIELDS = [
        'name', 'phone_number', 'rate', 'token', 'verification_nb',
        'player_id', 'image', 'email', 'qr_code',
        'district', 'town', 'sect', 'sect_number', 'date_of_birth', 'gender',
        'last_birthday_wish_sent_at',
    ];

    public function handle(): int
    {
        $commit = (bool) $this->option('commit');
        $limit = $this->option('limit');

        $this->info($commit
            ? 'Running for real — losing rows WILL be soft-deleted, canonical rows WILL be updated.'
            : 'DRY RUN — no changes will be written. Pass --commit to actually apply.');

        $duplicateMemberIds = AppUser::whereNull('deleted_at')
            ->select('member_id')
            ->groupBy('member_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('member_id');

        if ($limit) {
            $duplicateMemberIds = $duplicateMemberIds->take((int) $limit);
        }

        $this->info("Found {$duplicateMemberIds->count()} duplicate group(s) to process.");

        $groupsProcessed = 0;
        $rowsSoftDeleted = 0;
        $fieldsMerged = 0;
        $scanAccessRescued = 0;

        $progress = $this->output->createProgressBar($duplicateMemberIds->count());
        $progress->start();

        foreach ($duplicateMemberIds as $memberId) {
            $rows = AppUser::whereNull('deleted_at')
                ->where('member_id', $memberId)
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->get();

            if ($rows->count() < 2) {
                $progress->advance();
                continue; // race condition / already resolved since the id list was pulled
            }

            // Score: verified=1 is worth more than having a push token —
            // matches the investigation finding that "verified" is the
            // stronger signal of a row actually being someone's real,
            // currently-used account.
            $canonical = $rows->sortByDesc(function (AppUser $row) {
                return ($row->verified ? 2 : 0) + (!empty($row->player_id) ? 1 : 0);
            })->first();

            $losers = $rows->reject(fn(AppUser $row) => $row->id === $canonical->id);

            $anyScanAccess = $rows->contains(fn(AppUser $row) => (bool) $row->can_scan_checkin);
            if ($anyScanAccess && !$canonical->can_scan_checkin) {
                $scanAccessRescued++;
            }

            $mergedFieldsThisGroup = [];
            foreach ($losers as $loser) {
                foreach (self::MERGEABLE_FIELDS as $field) {
                    $canonicalValue = $canonical->{$field};
                    $loserValue = $loser->{$field};
                    $isEmpty = $canonicalValue === null || $canonicalValue === '';
                    if ($isEmpty && $loserValue !== null && $loserValue !== '') {
                        $mergedFieldsThisGroup[] = "member {$memberId}: {$field} from row {$loser->id} -> row {$canonical->id}";
                        if ($commit) {
                            $canonical->{$field} = $loserValue;
                        }
                    }
                }
            }
            $fieldsMerged += count($mergedFieldsThisGroup);

            if ($commit) {
                if ($anyScanAccess) {
                    $canonical->can_scan_checkin = true;
                }
                if (!empty($mergedFieldsThisGroup) || $anyScanAccess) {
                    $canonical->save();
                }
                foreach ($losers as $loser) {
                    $loser->delete(); // soft delete (SoftDeletes trait)
                }
            }

            $rowsSoftDeleted += $losers->count();
            $groupsProcessed++;

            if ($this->output->isVerbose()) {
                $this->line('');
                $this->line("member_id={$memberId}: keep row {$canonical->id}, soft-delete " . $losers->pluck('id')->implode(', '));
                foreach ($mergedFieldsThisGroup as $note) {
                    $this->line("  merged: {$note}");
                }
            }

            $progress->advance();
        }

        $progress->finish();
        $this->newLine(2);

        $this->table(
            ['Metric', 'Count'],
            [
                ['Duplicate groups processed', $groupsProcessed],
                ['Rows ' . ($commit ? 'soft-deleted' : 'that WOULD be soft-deleted'), $rowsSoftDeleted],
                ['Fields ' . ($commit ? 'merged' : 'that WOULD be merged'), $fieldsMerged],
                ['can_scan_checkin rescues', $scanAccessRescued],
            ]
        );

        if (!$commit) {
            $this->warn('This was a dry run — nothing was written. Re-run with --commit to actually apply.');
        }

        return 0;
    }
}
