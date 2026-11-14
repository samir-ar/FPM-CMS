<?php

namespace App\Exports;

use App\V2\InternalElectionVote;
use App\V2\FpmUser;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

// Full per-voter report for one Internal Election — who voted for whom,
// when, and their district/town. district/town come from fpm_users (the
// TWH-synced source of truth), not app_users, since app_users doesn't
// carry those columns.
class InternalElectionVotesExport implements FromCollection, WithHeadings, WithMapping
{
    private int $electionId;

    public function __construct(int $electionId)
    {
        $this->electionId = $electionId;
    }

    public function collection()
    {
        $votes = InternalElectionVote::with(['user', 'candidate'])
            ->where('internal_election_id', $this->electionId)
            ->get();

        $memberIds = $votes->pluck('user.member_id')->filter()->unique()->values();
        // app_users.member_id can carry a leading 0 that fpm_users.MemberId
        // doesn't (35 election-2 voters showed a blank district/town). Look up
        // by both forms and key by the zero-stripped id.
        $lookupIds = $memberIds->merge($memberIds->map(fn($i) => ltrim((string) $i, '0')))->unique()->values();
        $fpmUsers = FpmUser::whereIn('MemberId', $lookupIds)->get(['MemberId', 'district', 'town'])
            ->keyBy(fn($u) => ltrim((string) $u->MemberId, '0'));

        return $votes->map(function ($vote) use ($fpmUsers) {
            $fpmUser = $vote->user ? ($fpmUsers[ltrim((string) $vote->user->member_id, '0')] ?? null) : null;

            return (object) [
                'voter_name' => $vote->user->name ?? null,
                'member_id' => $vote->user->member_id ?? null,
                'candidate_name' => $vote->candidate->name ?? null,
                'voted_at' => $vote->voted_at,
                'district' => $fpmUser->district ?? null,
                'town' => $fpmUser->town ?? null,
            ];
        })->sortBy('district')->values();
    }

    public function headings(): array
    {
        return ['Voter', 'Member ID', 'District', 'Town', 'Candidate', 'Voted At'];
    }

    public function map($row): array
    {
        return [
            $row->voter_name,
            $row->member_id,
            $row->district,
            $row->town,
            $row->candidate_name,
            $row->voted_at,
        ];
    }
}
