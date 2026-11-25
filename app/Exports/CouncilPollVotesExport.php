<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

// Who voted for whom in a National Council poll (the restricted report).
class CouncilPollVotesExport implements FromArray, WithHeadings
{
    private array $rows;

    public function __construct(array $rows)
    {
        $this->rows = $rows;
    }

    public function headings(): array
    {
        return ['Voter', 'Member ID', 'Weight', 'Question', 'Voted for', 'Voted at', 'Phone model', 'Hardware ID', 'App install ID', 'Members who voted from this phone', 'App version', 'Phone of the app account'];
    }

    public function array(): array
    {
        return array_map(fn($r) => [
            $r['voter'], $r['member_id'], $r['weight'], $r['question'], $r['candidate'], $r['voted_at'],
            \App\Support\DeviceName::pretty($r['device_model'] ?? ''), $r['hardware'] ?? '', $r['device'], $r['device_members'] ?: '', $r['app_version'] ?? '', $r['phone'],
        ], $this->rows);
    }
}
