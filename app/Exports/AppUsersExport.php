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
        // fpm_users (synced nightly from TWH) is the source of truth for
        // district/town/sect/etc — not app_users' own now-unused columns.
        return AppUser::query()
            ->select([
                'app_users.name',
                'app_users.member_id',
                'app_users.phone_number',
                'app_users.email',
                'fpm_users.district',
                'fpm_users.town',
                'fpm_users.sect',
                'fpm_users.sect_number',
                'fpm_users.LastUnitPosition',
                'fpm_users.NashatUnit',
                'fpm_users.NoufousUnit',
            ])
            ->leftJoin('fpm_users', 'app_users.member_id', '=', 'fpm_users.MemberId')
            ->where('app_users.verified', 1);
    }

    public function headings(): array
    {
        return [
            "Name", "Member ID", "Phone Number", "Email",
            "District", "Town", "Sect", "Sect Number",
            "Position", "Activity Unit", "Civil Registry Unit",
        ];
    }

    public function map($user): array
    {
        return [
            $user->name,
            $user->member_id,
            $user->phone_number,
            $user->email,
            $user->district,
            $user->town,
            $user->sect,
            $user->sect_number,
            $user->LastUnitPosition,
            $user->NashatUnit,
            $user->NoufousUnit,
        ];
    }
}

