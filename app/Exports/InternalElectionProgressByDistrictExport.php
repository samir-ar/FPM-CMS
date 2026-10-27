<?php

namespace App\Exports;

use App\V2\InternalElectionPermission;
use App\V2\InternalElectionVote;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithCharts;
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\Layout;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title;

// Same permitted/voted/turnout computation as
// InternalElectionController::progress() - grouped by each permitted
// voter's own fpm_users.district value, not matched against
// election_states.name (see districtMatchesAnyState() for why that
// matching is fragile).
class InternalElectionProgressByDistrictExport implements FromCollection, WithHeadings, WithMapping, WithCharts
{
    private int $electionId;
    // Set by collection() so charts() knows the real cell range - charts()
    // runs after collection() in Laravel Excel's write pipeline, so this is
    // always populated by the time it's read.
    private int $rowCount = 0;

    public function __construct(int $electionId)
    {
        $this->electionId = $electionId;
    }

    public function collection()
    {
        $permittedMemberIds = InternalElectionPermission::where('election_id', $this->electionId)
            ->pluck('member_id')->filter()->unique()->values();

        $votedMemberIds = InternalElectionVote::with('user')
            ->where('internal_election_id', $this->electionId)
            ->get()
            ->pluck('user.member_id')
            ->filter()
            ->unique();
        $votedSet = $votedMemberIds->flip();

        $districtByMember = \DB::table('fpm_users')
            ->whereIn('MemberId', $permittedMemberIds)
            ->pluck('district', 'MemberId');

        $districtStats = [];
        foreach ($permittedMemberIds as $memberId) {
            $district = $districtByMember->get($memberId) ?: 'بدون دائرة';
            if (!isset($districtStats[$district])) {
                $districtStats[$district] = ['permitted' => 0, 'voted' => 0];
            }
            $districtStats[$district]['permitted']++;
            if ($votedSet->has($memberId)) {
                $districtStats[$district]['voted']++;
            }
        }

        $rows = collect($districtStats)->map(function ($stats, $district) {
            return (object) [
                'district' => $district,
                'permitted' => $stats['permitted'],
                'voted' => $stats['voted'],
                'remaining' => $stats['permitted'] - $stats['voted'],
                'turnout' => $stats['permitted'] > 0 ? round($stats['voted'] / $stats['permitted'] * 100, 1) : 0,
            ];
        })->sortByDesc('turnout')->values();

        $this->rowCount = $rows->count();

        return $rows;
    }

    public function charts()
    {
        if ($this->rowCount < 1) {
            return [];
        }

        $lastRow = $this->rowCount + 1; // +1 for the heading row

        $labels = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, 'Worksheet!$E$1', null, 1)];
        $categories = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "Worksheet!\$A\$2:\$A\${$lastRow}", null, $this->rowCount)];
        $values = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, "Worksheet!\$E\$2:\$E\${$lastRow}", null, $this->rowCount)];

        // Show the turnout % value on top of each bar. PhpSpreadsheet's
        // chart writer doesn't expose a text-rotation option for data
        // labels (checked the XML writer directly - no 'rot' attribute is
        // ever written), so with many bars these may need a manual
        // rotation in Excel afterward (right-click a label -> Format Data
        // Labels -> Text Options -> rotate).
        $dataLabelLayout = new Layout();
        $dataLabelLayout->setShowVal(true);
        $dataLabelLayout->setDLblPos('outEnd');
        $values[0]->setLabelLayout($dataLabelLayout);

        $series = new DataSeries(
            DataSeries::TYPE_BARCHART,
            DataSeries::GROUPING_CLUSTERED,
            range(0, count($values) - 1),
            $labels,
            $categories,
            $values
        );
        $series->setPlotDirection(DataSeries::DIRECTION_COL);

        $plotArea = new PlotArea(null, [$series]);
        $legend = new Legend(Legend::POSITION_RIGHT, null, false);
        $title = new Title('Turnout % by District');

        $chart = new Chart('turnout-by-district', $title, $legend, $plotArea);
        $chart->setTopLeftPosition('G2');
        $chart->setBottomRightPosition('P20');

        return $chart;
    }

    public function headings(): array
    {
        return ['District', 'Permitted', 'Voted', 'Remaining', 'Turnout %'];
    }

    public function map($row): array
    {
        // Cast to string - Laravel Excel's write pipeline collapses a
        // literal numeric 0 to a NULL/blank cell otherwise (confirmed via
        // direct testing: map() returns int/float 0 correctly, but the
        // written .xlsx cell comes back NULL for exactly those values).
        return [
            $row->district,
            (string) $row->permitted,
            (string) $row->voted,
            (string) $row->remaining,
            (string) $row->turnout,
        ];
    }
}
