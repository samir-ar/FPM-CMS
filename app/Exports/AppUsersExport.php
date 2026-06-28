<?php

namespace App\Exports;

use App\AppUser;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class AppUsersExport implements FromQuery, WithHeadings, WithMapping
{
    public function query()
    {
        return AppUser::query()->select(['name', 'member_id', 'phone_number', 'email'])
            ->where('verified', 1);
    }

    public function headings(): array
    {
        return ["Name", "Member ID", "Phone Number", "Email"];
    }

    public function map($user): array
    {
        return [
            $user->name,
            $user->member_id,
            $user->phone_number,
            $user->email,
        ];
    }
}

