<?php

namespace App\Imports;

use App\V2\InternalElectionPermission;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;

class InternalElectionAllowedVotersImport implements ToCollection
{
    public $electionId;

    public function __construct($electionId)
    {
        ini_set('max_execution_time', 2700);
        ini_set('memory_limit', '-1');
        $this->electionId = $electionId;
    }

    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            $memberId = trim((string) ($row[0] ?? ''));

            // Skips blank rows and the template's own header row (a real
            // FPM member ID is always numeric).
            if ($memberId === '' || !ctype_digit($memberId)) {
                continue;
            }

            InternalElectionPermission::firstOrCreate([
                'election_id' => $this->electionId,
                'member_id' => $memberId,
            ]);
        }
    }
}
