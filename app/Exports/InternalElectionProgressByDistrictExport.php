<?php

namespace App\Exports;

use App\V2\InternalElectionPermission;
use App\V2\InternalElectionVote;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

// Same permitted/voted/turnout computation as
// InternalElectionController::progress() - grouped by each permitted
// voter's own fpm_users.district value, not matched against
// election_states.name (see districtMatchesAnyState() for why that
// matching is fragile).
class InternalElectionProgressByDistrictExport implements FromCollection, WithHeadings, WithMapping
{
    private int $electionId;

    public function __construct(int $electionId)
    {
        $this->electionId = $electionId;
    }

    public function collection()
    {
        $permittedMemberIds = InternalElectionPermission::where('election_id', $this->electionId)
            ->pluck('member_id')->filter()->unique()->values();

        $votedMemberIds = InternalElectionVote::with('user')
            ->where('internal_election_id', $this->electionId)
            ->get()
            ->pluck('user.member_id')
            ->filter()
            ->unique();
        $votedSet = $votedMemberIds->flip();

        $districtByMember = \DB::table('fpm_users')
            ->whereIn('MemberId', $permittedMemberIds)
            ->pluck('district', 'MemberId');

        $districtStats = [];
        foreach ($permittedMemberIds as $memberId) {
            $district = $districtByMember->get($memberId) ?: 'بدون دائرة';
            if (!isset($districtStats[$district])) {
                $districtStats[$district] = ['permitted' => 0, 'voted' => 0];
            }
            $districtStats[$district]['permitted']++;
            if ($votedSet->has($memberId)) {
                $districtStats[$district]['voted']++;
            }
        }

        return collect($districtStats)->map(function ($stats, $district) {
            return (object) [
                'district' => $district,
                'permitted' => $stats['permitted'],
                'voted' => $stats['voted'],
                'remaining' => $stats['permitted'] - $stats['voted'],
                'turnout' => $stats['permitted'] > 0 ? round($stats['voted'] / $stats['permitted'] * 100, 1) : 0,
            ];
        })->sortBy('turnout')->values();
    }

    public function headings(): array
    {
        return ['District', 'Permitted', 'Voted', 'Remaining', 'Turnout %'];
    }

    public function map($row): array
    {
        // Cast to string - Laravel Excel's write pipeline collapses a
        // literal numeric 0 to a NULL/blank cell otherwise (confirmed via
        // direct testing: map() returns int/float 0 correctly, but the
        // written .xlsx cell comes back NULL for exactly those values).
        return [
            $row->district,
            (string) $row->permitted,
            (string) $row->voted,
            (string) $row->remaining,
            (string) $row->turnout,
        ];
    }
}
