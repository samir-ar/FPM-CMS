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

    private function buildRows(): array
    {
        $memberCounts = FpmUser::whereNotNull('district')->where('district', '!=', '')
            ->select('district', DB::raw('COUNT(*) as cnt'))
            ->groupBy('district')
            ->pluck('cnt', 'district');

        $installedCounts = DB::table('app_users')
            ->join('fpm_users', 'app_users.member_id', '=', 'fpm_users.MemberId')
            ->whereNull('app_users.deleted_at')
            ->where('app_users.verified', 1)
            ->whereNotNull('fpm_users.district')
            ->where('fpm_users.district', '!=', '')
            ->select('fpm_users.district', DB::raw('COUNT(DISTINCT app_users.member_id) as cnt'))
            ->groupBy('fpm_users.district')
            ->pluck('cnt', 'district');

        $rows = [];
        foreach (self::GROUPS as $label => $rawDistricts) {
            $totalMember = 0;
            $totalInstalled = 0;
            foreach ($rawDistricts as $raw) {
                $totalMember += (int) ($memberCounts[$raw] ?? 0);
                $totalInstalled += (int) ($installedCounts[$raw] ?? 0);
            }
            $rows[] = [
                'kada' => $label,
                'total_installed' => $totalInstalled,
                'total_member' => $totalMember,
                'percentage' => $totalMember > 0 ? round($totalInstalled / $totalMember * 100, 2) : 0,
            ];
        }

        return $rows;
    }

    public function build(): Spreadsheet
    {
        $rows = $this->buildRows();
        $date = now()->format('j-M-y');

        $byInstalled = collect($rows)->sortByDesc('total_installed')->values()->all();
        $byMember = collect($rows)->sortByDesc('total_member')->values()->all();
        $byPercentage = collect($rows)->sortByDesc('percentage')->values()->all();

        $totalInstalled = array_sum(array_column($rows, 'total_installed'));
        $totalMember = array_sum(array_column($rows, 'total_member'));
        $totalPercentage = $totalMember > 0 ? round($totalInstalled / $totalMember * 100, 2) : 0;

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $tables = [
            ['startCol' => 1, 'title' => 'Table Number 1', 'subtitle' => 'Total App installed per casaa', 'sortLabel' => 'Mobile app updates', 'data' => $byInstalled, 'sortField' => 'total_installed'],
            ['startCol' => 6, 'title' => 'Table Number 2', 'subtitle' => 'Total ID FPM per casaa', 'sortLabel' => 'Total Members - Descending', 'data' => $byMember, 'sortField' => 'total_member'],
            ['startCol' => 11, 'title' => 'Table Number 3', 'subtitle' => 'Total Percentage Nm. ID/App installed', 'sortLabel' => 'Percentage Descending', 'data' => $byPercentage, 'sortField' => 'percentage'],
        ];

        foreach ($tables as $table) {
            $this->writeTable($sheet, $table, $date, $totalInstalled, $totalMember, $totalPercentage);
        }

        foreach (range('A', 'N') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return $spreadsheet;
    }

    private function writeTable($sheet, array $table, string $date, int $totalInstalled, int $totalMember, float $totalPercentage): void
    {
        $c0 = $table['startCol'];
        $c1 = $c0 + 1;
        $c2 = $c0 + 2;
        $c3 = $c0 + 3;
        $col0 = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c0);
        $col1 = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c1);
        $col2 = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c2);
        $col3 = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c3);

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
        };

        // Row 1-3: title / subtitle / sort-label + date
        $sheet->setCellValue("{$col0}1", $table['title']);
        $sheet->mergeCells("{$col0}1:{$col3}1");
        $sheet->setCellValue("{$col0}2", $table['subtitle']);
        $sheet->mergeCells("{$col0}2:{$col3}2");
        $sheet->setCellValue("{$col0}3", $table['sortLabel']);
        $sheet->setCellValue("{$col1}3", $date);
        $sheet->mergeCells("{$col1}3:{$col3}3");
        $sheet->getStyle("{$col0}1:{$col3}3")->applyFromArray($centerBold);
        $sheet->getStyle("{$col0}1:{$col3}3")->applyFromArray($thinBorder);

        // Row 4: headers
        $sheet->setCellValue("{$col0}4", 'Kada2');
        $sheet->setCellValue("{$col1}4", 'total_installed');
        $sheet->setCellValue("{$col2}4", 'total_member');
        $sheet->setCellValue("{$col3}4", 'total_installed_percentage');
        $sheet->getStyle("{$col0}4:{$col3}4")->applyFromArray($centerBold);
        $sheet->getStyle("{$col0}4:{$col3}4")->applyFromArray($thinBorder);

        // Data rows
        $row = 5;
        foreach ($table['data'] as $r) {
            $sheet->setCellValue("{$col0}{$row}", $r['kada']);
            $sheet->setCellValue("{$col1}{$row}", $r['total_installed']);
            $sheet->setCellValue("{$col2}{$row}", $r['total_member']);
            $sheet->setCellValue("{$col3}{$row}", $r['percentage']);
            $sheet->getStyle("{$sortCol}{$row}")->applyFromArray(['fill' => $headerFill]);
            $sheet->getStyle("{$col0}{$row}:{$col3}{$row}")->applyFromArray($thinBorder);
            $row++;
        }

        // TOTAL row
        $sheet->setCellValue("{$col0}{$row}", 'TOTAL');
        $sheet->setCellValue("{$col1}{$row}", $totalInstalled);
        $sheet->setCellValue("{$col2}{$row}", $totalMember);
        $sheet->setCellValue("{$col3}{$row}", $totalPercentage);
        $sheet->getStyle("{$col0}{$row}:{$col3}{$row}")->applyFromArray($centerBold);
        $sheet->getStyle("{$sortCol}{$row}")->applyFromArray(['fill' => $headerFill]);
        $sheet->getStyle("{$col0}{$row}:{$col3}{$row}")->applyFromArray($thinBorder);
    }
}
