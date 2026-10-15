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
        $fpmUsers = FpmUser::whereIn('MemberId', $memberIds)->get(['MemberId', 'district', 'town'])->keyBy('MemberId');

        return $votes->map(function ($vote) use ($fpmUsers) {
            $fpmUser = $vote->user ? ($fpmUsers[$vote->user->member_id] ?? null) : null;

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
        return ['Voter', 'Member ID', 'Voted At', 'District', 'Town', 'Candidate'];
    }

    public function map($row): array
    {
        return [
            $row->voter_name,
            $row->member_id,
            $row->voted_at,
            $row->district,
            $row->town,
            $row->candidate_name,
        ];
    }
}
