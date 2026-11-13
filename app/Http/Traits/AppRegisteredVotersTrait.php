<?php

namespace App\Http\Traits;

use App\V2\AppUser;
use Illuminate\Support\Collection;

/**
 * Shared by the admin Election Progress page and the standalone turnout page:
 * the "registered and logged in to the app" view of the permitted voters,
 * shown as a second block under the original all-permitted-voters one.
 *
 * "Registered and logged in" = a live (non-deleted) app_users row that is
 * verified, has an active member_status and currently holds a login token
 * (a token only exists after a completed phone-code login; logout clears it).
 * Anyone who has already voted in the election is also counted as
 * registered, so "voted" can never exceed the registered total even if a
 * voter logged out (token cleared) after voting.
 *
 * member_id is compared with leading zeros stripped on both sides, same
 * tolerance canIVote() already applies to the permitted-voters list.
 */
trait AppRegisteredVotersTrait
{
    /** Set (as keys) of leading-zero-stripped member_ids that are registered and logged in. */
    protected function registeredMemberSet(Collection $votedMemberIds): Collection
    {
        $ids = AppUser::query()
            ->where('verified', 1)
            ->where('member_status', 1)
            ->whereNotNull('token')
            ->where('token', '<>', '')
            ->pluck('member_id');

        return $ids->merge($votedMemberIds)
            ->filter()
            ->map(fn($id) => ltrim((string) $id, '0'))
            ->unique()
            ->flip();
    }

    protected function isAppRegistered($memberId, Collection $registeredSet): bool
    {
        return $registeredSet->has(ltrim((string) $memberId, '0'));
    }

    /**
     * Totals + per-district rows for the registered-in-app block.
     * $permittedMemberIds: the same election-district-scoped list the
     * original block uses. $votedSet: voted member_ids flipped to keys.
     */
    protected function appRegisteredStats(Collection $permittedMemberIds, Collection $districtByMember, Collection $votedMemberIds, Collection $votedSet): array
    {
        $registeredSet = $this->registeredMemberSet($votedMemberIds);

        $registeredIds = $permittedMemberIds
            ->filter(fn($memberId) => $this->isAppRegistered($memberId, $registeredSet))
            ->values();

        $total = $registeredIds->count();
        $voted = $registeredIds->intersect($votedMemberIds)->count();

        $byDistrict = [];
        foreach ($registeredIds as $memberId) {
            $district = $districtByMember->get($memberId) ?: 'بدون دائرة';
            if (!isset($byDistrict[$district])) {
                $byDistrict[$district] = ['permitted' => 0, 'voted' => 0];
            }
            $byDistrict[$district]['permitted']++;
            if ($votedSet->has($memberId)) {
                $byDistrict[$district]['voted']++;
            }
        }

        $rows = collect($byDistrict)->map(function ($stats, $district) {
            return [
                'district' => $district,
                'permitted' => $stats['permitted'],
                'voted' => $stats['voted'],
                'remaining' => $stats['permitted'] - $stats['voted'],
                'turnout' => $stats['permitted'] > 0 ? round($stats['voted'] / $stats['permitted'] * 100, 1) : 0,
            ];
        })->sortByDesc('turnout')->values();

        return [
            'appTotal' => $total,
            'appVoted' => $voted,
            'appRemaining' => $total - $voted,
            'appTurnout' => $total > 0 ? round($voted / $total * 100, 1) : 0,
            'appDistrictRows' => $rows,
        ];
    }
}
