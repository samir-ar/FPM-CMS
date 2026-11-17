<?php

namespace App\Imports;

use App\Exceptions\InvalidVotersFileException;
use App\Support\CouncilMemberLookup;
use App\V2\CouncilRosterMember;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;

// Columns: Member ID | Weight | Role (optional) | Sector (optional) | District (optional)
// ADDITIVE: rows are added or updated by member id, nobody is removed (unlike
// the poll's own re-upload, which replaces its whole list) - the roster is a
// long-lived list and one bad file must never wipe it.
class CouncilRosterImport implements ToCollection
{
    public $added = 0;
    public $updated = 0;
    public $skipped = 0;

    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            $memberId = CouncilMemberLookup::normalizeMemberId($row[0] ?? '');

            // blank rows and the header row
            if ($memberId === '') {
                $this->skipped++;
                continue;
            }

            $weight = trim((string) ($row[1] ?? ''));
            if ($weight === '' || !ctype_digit($weight)) {
                throw new InvalidVotersFileException(
                    'يجب إدخال رقم الانتساب (Member ID) والوزن (Weight) لكل عضو. المشكلة عند الرقم: ' . $memberId
                );
            }

            $data = ['weight' => (int) $weight, 'is_active' => true];
            foreach (['role' => 2, 'sector' => 3, 'district' => 4] as $field => $idx) {
                $v = trim((string) ($row[$idx] ?? ''));
                if ($v !== '') {
                    $data[$field] = $v;
                }
            }

            $existing = CouncilRosterMember::where('member_id', $memberId)->first();
            if ($existing) {
                $existing->update($data);
                $this->updated++;
            } else {
                CouncilRosterMember::create($data + ['member_id' => $memberId]);
                $this->added++;
            }
        }
    }
}
