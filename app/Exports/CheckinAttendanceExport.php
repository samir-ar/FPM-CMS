<?php

namespace App\Exports;

use App\V2\EventAttendance;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class CheckinAttendanceExport implements FromQuery, WithHeadings, WithMapping
{
    private int $eventId;

    public function __construct(int $eventId)
    {
        $this->eventId = $eventId;
    }

    public function query()
    {
        // fpm_users is the source of truth for district/town/mobile/position,
        // same join pattern as AppUsersExport.
        return EventAttendance::query()
            ->select([
                'event_attendance.member_id',
                'fpm_users.UserFullName',
                'fpm_users.MobileNumber',
                'fpm_users.district',
                'fpm_users.town',
                'fpm_users.LastUnitPosition',
                'event_attendance.checked_in_at',
            ])
            ->leftJoin('fpm_users', 'event_attendance.member_id', '=', 'fpm_users.MemberId')
            ->where('event_attendance.event_id', $this->eventId)
            ->orderByDesc('event_attendance.checked_in_at');
    }

    public function headings(): array
    {
        return [
            "Member ID", "Name", "Mobile Number",
            "District", "Town", "Position", "Check-In Time",
        ];
    }

    public function map($row): array
    {
        return [
            $row->member_id,
            $row->UserFullName,
            $row->MobileNumber,
            $row->district,
            $row->town,
            $row->LastUnitPosition,
            $row->checked_in_at,
        ];
    }
}
