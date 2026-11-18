<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

// Who may vote in a National Council poll and who already has (never WHAT anybody voted).
class CouncilPollVotersExport implements FromArray, WithHeadings
{
    private array $rows;

    public function __construct(array $rows)
    {
        $this->rows = $rows;
    }

    public function headings(): array
    {
        return ['Member ID', 'Name', 'Role', 'Weight', 'Voted', 'Voted at', 'Phone model', 'Hardware ID', 'App install ID', 'Members who voted from this phone', 'Phone of the app account', 'On the voter list'];
    }

    public function array(): array
    {
        return array_map(fn($r) => [
            $r['member_id'], $r['name'], $r['role'], $r['weight'], $r['voted'] ? 'Yes' : 'No', $r['voted_at'],
            $r['device_model'] ?? '', $r['hardware'] ?? '', $r['device'], $r['device_members'] ?: '', $r['phone'], $r['permitted'] ? 'Yes' : 'NO (removed or never added)',
        ], $this->rows);
    }
}
