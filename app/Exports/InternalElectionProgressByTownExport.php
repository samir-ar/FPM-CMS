<?php

namespace App\Exports;

use App\V2\InternalElectionPermission;
use App\V2\InternalElectionVote;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

// Full nationwide district+town breakdown, same permitted/voted/turnout
// computation as InternalElectionController::progressTowns() but across
// every district at once rather than one clicked district - one row per
// (district, town) pair this election has permitted voters in.
class InternalElectionProgressByTownExport implements FromCollection, WithHeadings, WithMapping
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

        $fpmRows = \DB::table('fpm_users')
            ->whereIn('MemberId', $permittedMemberIds)
            ->get(['MemberId', 'district', 'town'])
            ->keyBy('MemberId');

        $stats = [];
        foreach ($permittedMemberIds as $memberId) {
            $row = $fpmRows->get($memberId);
            $district = ($row && $row->district) ? $row->district : 'بدون دائرة';
            $town = ($row && $row->town) ? $row->town : 'بدون بلدة';
            $key = $district . '|' . $town;

            if (!isset($stats[$key])) {
                $stats[$key] = ['district' => $district, 'town' => $town, 'permitted' => 0, 'voted' => 0];
            }
            $stats[$key]['permitted']++;
            if ($votedSet->has($memberId)) {
                $stats[$key]['voted']++;
            }
        }

        return collect($stats)->map(function ($s) {
            return (object) [
                'district' => $s['district'],
                'town' => $s['town'],
                'permitted' => $s['permitted'],
                'voted' => $s['voted'],
                'remaining' => $s['permitted'] - $s['voted'],
                'turnout' => $s['permitted'] > 0 ? round($s['voted'] / $s['permitted'] * 100, 1) : 0,
            ];
        })->sortBy(fn($r) => [$r->district, $r->turnout])->values();
    }

    public function headings(): array
    {
        return ['District', 'Town', 'Permitted', 'Voted', 'Remaining', 'Turnout %'];
    }

    public function map($row): array
    {
        // Cast to string - see InternalElectionProgressByDistrictExport for
        // why: Laravel Excel's write pipeline collapses a literal numeric 0
        // to a NULL/blank cell otherwise.
        return [
            $row->district,
            $row->town,
            (string) $row->permitted,
            (string) $row->voted,
            (string) $row->remaining,
            (string) $row->turnout,
        ];
    }
}
