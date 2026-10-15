<?php

namespace App\Imports;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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

    // Bulk-upserts in chunks instead of one firstOrCreate() (1 SELECT + 1
    // INSERT) per row - a real ~35,000-row list meant ~70,000 individual
    // queries, slow enough to blow past the gateway's timeout long before
    // PHP itself (given 45 minutes via max_execution_time above) actually
    // finished. Chunked upserts turn that into ~a dozen queries total.
    public function collection(Collection $rows)
    {
        $now = Carbon::now();
        $batch = [];

        foreach ($rows as $row) {
            $memberId = trim((string) ($row[0] ?? ''));

            // Skips blank rows and the template's own header row (a real
            // FPM member ID is always numeric).
            if ($memberId === '' || !ctype_digit($memberId)) {
                continue;
            }

            $batch[] = [
                'election_id' => $this->electionId,
                'member_id' => $memberId,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($batch) >= 2000) {
                DB::table('internal_election_permissions')->upsert($batch, ['election_id', 'member_id'], ['updated_at']);
                $batch = [];
            }
        }

        if (!empty($batch)) {
            DB::table('internal_election_permissions')->upsert($batch, ['election_id', 'member_id'], ['updated_at']);
        }
    }
}
