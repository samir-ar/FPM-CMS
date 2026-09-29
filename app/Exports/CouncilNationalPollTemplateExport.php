<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;

class CouncilNationalPollTemplateExport implements FromArray
{
    public function array(): array
    {
        return [
            ['member_id', 'weight'],
            ['202400199', '1'],
            ['203700169', '2'],
        ];
    }
}
