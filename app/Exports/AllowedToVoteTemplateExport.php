<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;

class AllowedToVoteTemplateExport implements FromArray
{
    public function array(): array
    {
        return [
            ['member_id'],
            ['202400199'],
            ['203700169'],
        ];
    }
}
