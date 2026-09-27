<?php

namespace App\Imports;

use App\V2\AppUser;
use App\V2\FpmUser;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;

// One column: FPM member_id. Every member_id found in the file gets
// Allowed_to_vote=1 on both fpm_users and app_users — purely additive,
// members already flagged from a previous import (or not in this file
// at all) are left untouched.
class AllowedToVoteImport implements ToCollection
{
    public int $matched = 0;
    public int $notFound = 0;

    public function collection(Collection $rows)
    {
        foreach ($rows as $index => $row) {
            $memberId = trim((string) ($row[0] ?? ''));

            if ($memberId === '' || !is_numeric($memberId)) {
                continue; // header row / blank row
            }

            $fpmUpdated = FpmUser::where('MemberId', $memberId)->update(['Allowed_to_vote' => 1]);
            $appUpdated = AppUser::where('member_id', $memberId)->update(['Allowed_to_vote' => 1]);

            if ($fpmUpdated || $appUpdated) {
                $this->matched++;
            } else {
                $this->notFound++;
            }
        }
    }
}
