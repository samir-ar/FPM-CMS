<?php

namespace App\Exports;

use App\V2\FpmUser;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Per-قضاء app-install coverage: how many of each district's fpm_users
 * roster actually have an active, verified app_users account. Three views
 * of the same rows (sorted by installs / by roster size / by percentage),
 * matching a reference report the user maintained by hand.
 *
 * Kada2 groupings are hardcoded to match that reference report exactly —
 * they don't derive cleanly from any single existing table (electoral_districts
 * merges some qadas differently, e.g. it doesn't merge صور+بنت جبيل at all).
 * Beirut and Sidon are each kept as one combined row (simplification agreed
 * with the user — fpm_users.district doesn't carry the finer split either
 * report used).
 */
class AppInstallationReportExport
{
    private const GROUPS = [
        'كسروان' => ['كسروان'],
        'جبيل' => ['جبيل'],
        'عكار' => ['عكار'],
        'بعبدا' => ['بعبدا'],
        'الشوف' => ['الشوف'],
        'بعلبك - الهرمل' => ['بعلبك', 'الهرمل'],
        'عاليه' => ['عاليه'],
        'المتن الشمالي' => ['المتن الشمالي'],
        'البترون' => ['البترون'],
        'جزين' => ['جزين'],
        'زحلة' => ['زحلة'],
        'بيروت' => ['بيروت'],
        'صيدا' => ['صيدا'],
        'زغرتا' => ['زغرتا'],
        'مرجعيون - حاصبيا' => ['مرجعيون', 'حاصبيا'],
        'الكورة' => ['الكورة'],
        'صور - بنت جبيل' => ['صور', 'بنت جبيل'],
        'البقاع الغربي' => ['البقاع الغربي'],
        'طرابلس' => ['طرابلس'],
        'بشري' => ['بشري'],
        'النبطية' => ['النبطية'],
        'راشيا' => ['راشيا'],
        'المنية-الضنية' => ['المنية-الضنية'],
    ];

    /** App activity is only recorded from this date (the activity log was created then). */
    public const TRACKING_START = '2026-09-24';

    private int $year;
    private int $month;
    private array $filters;

    /**
     * $filters (all optional): district (one of the GROUPS labels), town, sect, unit (Activity Unit), member_id.
     * The month decides "installed by the end of that month" and "active during that month".
     */
    public function __construct(?int $year = null, ?int $month = null, array $filters = [])
    {
        $now = now(config('app.timezone'));
        $this->year = $year ?: (int) $now->year;
        $this->month = $month ?: (int) $now->month;
        $this->filters = array_map(fn($v) => trim((string) $v), $filters);
    }

    public function monthLabel(): string
    {
        return \Carbon\Carbon::create($this->year, $this->month, 1)->format('M Y');
    }

    public static function groupLabels(): array
    {
        return array_keys(self::GROUPS);
    }

    /** The filters that narrow the member list (the district filter picks the rows instead). */
    private function applyFilters($query)
    {
        $f = $this->filters;
        foreach (['town' => 'fpm_users.town', 'sect' => 'fpm_users.sect', 'unit' => 'fpm_users.NashatUnit'] as $key => $column) {
            if (($f[$key] ?? '') !== '') {
                $query->where($column, 'like', '%' . str_replace(['%', '_'], ['\%', '\_'], $f[$key]) . '%');
            }
        }
        // the district as the member list spells it (the App Users filter), or one of the report's merged names
        if (($f['district'] ?? '') !== '') {
            $raws = self::GROUPS[$f['district']] ?? [$f['district']];
            $query->whereIn('fpm_users.district', $raws);
        }
        if (($f['member_id'] ?? '') !== '') {
            $query->where('fpm_users.MemberId', $f['member_id']);
        }

        return $query;
    }

    /** Rows: kada, total_installed (by the end of the month), total_member, percentage, total_active (in the month), active_percentage. */
    public function buildRows(): array
    {
        $tz = config('app.timezone');
        $monthStart = \Carbon\Carbon::create($this->year, $this->month, 1, 0, 0, 0, $tz);
        $monthEnd = $monthStart->copy()->endOfMonth();
        $activeFrom = max($monthStart->toDateString(), self::TRACKING_START);

        $memberCounts = $this->applyFilters(DB::table('fpm_users')->whereNotNull('fpm_users.district')->where('fpm_users.district', '!=', ''))
            ->select('fpm_users.district', DB::raw('COUNT(*) as cnt'))
            ->groupBy('fpm_users.district')
            ->pluck('cnt', 'district');

        $installedCounts = $this->applyFilters(DB::table('app_users')
            ->join('fpm_users', 'app_users.member_id', '=', 'fpm_users.MemberId')
            ->whereNull('app_users.deleted_at')
            ->where('app_users.verified', 1)
            // installs counted from the day tracking began (24 Sept 2026, Beirut time) up to the end of the chosen month
            ->where('app_users.created_at', '>=', \Carbon\Carbon::parse(self::TRACKING_START, $tz)->startOfDay()->setTimezone('UTC'))
            ->where('app_users.created_at', '<=', $monthEnd->copy()->setTimezone('UTC'))
            ->whereNotNull('fpm_users.district')
            ->where('fpm_users.district', '!=', ''))
            ->select('fpm_users.district', DB::raw('COUNT(DISTINCT app_users.member_id) as cnt'))
            ->groupBy('fpm_users.district')
            ->pluck('cnt', 'district');

        // active = opened the app at least one day of the month (an account replaced later still counts for its member)
        $activeCounts = $activeFrom > $monthEnd->toDateString() ? collect() : $this->applyFilters(DB::table('user_activity_logs')
            ->join('app_users', 'app_users.id', '=', 'user_activity_logs.app_user_id')
            ->join('fpm_users', 'app_users.member_id', '=', 'fpm_users.MemberId')
            ->whereBetween('user_activity_logs.activity_date', [$activeFrom, $monthEnd->toDateString()])
            ->whereNotNull('fpm_users.district')
            ->where('fpm_users.district', '!=', ''))
            ->select('fpm_users.district', DB::raw('COUNT(DISTINCT app_users.member_id) as cnt'))
            ->groupBy('fpm_users.district')
            ->pluck('cnt', 'district');

        $only = ($this->filters['district'] ?? '') !== '' ? $this->filters['district'] : null;
        $rows = [];
        foreach (self::GROUPS as $label => $rawDistricts) {
            if ($only !== null && $label !== $only && !in_array($only, $rawDistricts, true)) {
                continue;
            }
            $totalMember = 0;
            $totalInstalled = 0;
            $totalActive = 0;
            foreach ($rawDistricts as $raw) {
                $totalMember += (int) ($memberCounts[$raw] ?? 0);
                $totalInstalled += (int) ($installedCounts[$raw] ?? 0);
                $totalActive += (int) ($activeCounts[$raw] ?? 0);
            }
            $rows[] = [
                'kada' => $label,
                'total_installed' => $totalInstalled,
                'total_member' => $totalMember,
                'percentage' => $totalMember > 0 ? round($totalInstalled / $totalMember * 100, 2) : 0,
                'total_active' => $totalActive,
                'active_percentage' => $totalMember > 0 ? round($totalActive / $totalMember * 100, 2) : 0,
            ];
        }

        return $rows;
    }

    public function build(): Spreadsheet
    {
        $rows = $this->buildRows();
        $date = $this->monthLabel();

        $byInstalled = collect($rows)->sortByDesc('total_installed')->values()->all();
        $byMember = collect($rows)->sortByDesc('total_member')->values()->all();
        $byPercentage = collect($rows)->sortByDesc('percentage')->values()->all();

        $totalInstalled = array_sum(array_column($rows, 'total_installed'));
        $totalMember = array_sum(array_column($rows, 'total_member'));
        $totalPercentage = $totalMember > 0 ? round($totalInstalled / $totalMember * 100, 2) : 0;
        $totalActive = array_sum(array_column($rows, 'total_active'));
        $totalActivePercentage = $totalMember > 0 ? round($totalActive / $totalMember * 100, 2) : 0;

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $tables = [
            ['startCol' => 1, 'title' => 'Table Number 1', 'subtitle' => 'Total App installed per casaa', 'sortLabel' => 'Mobile app updates', 'data' => $byInstalled, 'sortField' => 'total_installed'],
            ['startCol' => 8, 'title' => 'Table Number 2', 'subtitle' => 'Total ID FPM per casaa', 'sortLabel' => 'Total Members - Descending', 'data' => $byMember, 'sortField' => 'total_member'],
            ['startCol' => 15, 'title' => 'Table Number 3', 'subtitle' => 'Total Percentage Nm. ID/App installed', 'sortLabel' => 'Percentage Descending', 'data' => $byPercentage, 'sortField' => 'percentage'],
        ];

        foreach ($tables as $table) {
            $this->writeTable($sheet, $table, $date, $totalInstalled, $totalMember, $totalPercentage, $totalActive, $totalActivePercentage);
        }

        foreach (range('A', 'T') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return $spreadsheet;
    }

    private function writeTable($sheet, array $table, string $date, int $totalInstalled, int $totalMember, float $totalPercentage, int $totalActive = 0, float $totalActivePercentage = 0): void
    {
        $c0 = $table['startCol'];
        $c1 = $c0 + 1;
        $c2 = $c0 + 2;
        $c3 = $c0 + 3;
        $col0 = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c0);
        $col1 = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c1);
        $col2 = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c2);
        $col3 = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c3);
        $col4 = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c0 + 4);
        $col5 = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c0 + 5);

        $headerFill = [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'FFC000'],
        ];
        $thinBorder = [
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ];
        $centerBold = ['alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER], 'font' => ['bold' => true]];

        // Only the column this table is actually sorted by gets highlighted —
        // not all three data columns.
        $sortCol = match ($table['sortField']) {
            'total_installed' => $col1,
            'total_member' => $col2,
            'percentage' => $col3,
            'total_active' => $col4,
        };

        // Row 1-3: title / subtitle / sort-label + date
        $sheet->setCellValue("{$col0}1", $table['title']);
        $sheet->mergeCells("{$col0}1:{$col5}1");
        $sheet->setCellValue("{$col0}2", $table['subtitle']);
        $sheet->mergeCells("{$col0}2:{$col5}2");
        $sheet->setCellValue("{$col0}3", $table['sortLabel']);
        $sheet->setCellValue("{$col1}3", $date);
        $sheet->mergeCells("{$col1}3:{$col5}3");
        $sheet->getStyle("{$col0}1:{$col5}3")->applyFromArray($centerBold);
        $sheet->getStyle("{$col0}1:{$col5}3")->applyFromArray($thinBorder);

        // Row 4: headers
        $sheet->setCellValue("{$col0}4", 'Kada2');
        $sheet->setCellValue("{$col1}4", 'total_installed');
        $sheet->setCellValue("{$col2}4", 'total_member');
        $sheet->setCellValue("{$col3}4", 'total_installed_percentage');
        $sheet->setCellValue("{$col4}4", 'total_active');
        $sheet->setCellValue("{$col5}4", 'total_active_percentage');
        $sheet->getStyle("{$col0}4:{$col5}4")->applyFromArray($centerBold);
        $sheet->getStyle("{$col0}4:{$col5}4")->applyFromArray($thinBorder);

        // Data rows
        $row = 5;
        foreach ($table['data'] as $r) {
            $sheet->setCellValue("{$col0}{$row}", $r['kada']);
            $sheet->setCellValue("{$col1}{$row}", $r['total_installed']);
            $sheet->setCellValue("{$col2}{$row}", $r['total_member']);
            $sheet->setCellValue("{$col3}{$row}", $r['percentage']);
            $sheet->setCellValue("{$col4}{$row}", $r['total_active']);
            $sheet->setCellValue("{$col5}{$row}", $r['active_percentage']);
            $sheet->getStyle("{$sortCol}{$row}")->applyFromArray(['fill' => $headerFill]);
            $sheet->getStyle("{$col0}{$row}:{$col5}{$row}")->applyFromArray($thinBorder);
            $row++;
        }

        // TOTAL row
        $sheet->setCellValue("{$col0}{$row}", 'TOTAL');
        $sheet->setCellValue("{$col1}{$row}", $totalInstalled);
        $sheet->setCellValue("{$col2}{$row}", $totalMember);
        $sheet->setCellValue("{$col3}{$row}", $totalPercentage);
        $sheet->setCellValue("{$col4}{$row}", $totalActive);
        $sheet->setCellValue("{$col5}{$row}", $totalActivePercentage);
        $sheet->getStyle("{$col0}{$row}:{$col5}{$row}")->applyFromArray($centerBold);
        $sheet->getStyle("{$sortCol}{$row}")->applyFromArray(['fill' => $headerFill]);
        $sheet->getStyle("{$col0}{$row}:{$col5}{$row}")->applyFromArray($thinBorder);
    }
}
