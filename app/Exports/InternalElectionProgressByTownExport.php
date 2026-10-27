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

// Full nationwide district+town breakdown, same permitted/voted/turnout
// computation as InternalElectionController::progressTowns() but across
// every district at once rather than one clicked district - one row per
// (district, town) pair this election has permitted voters in.
class InternalElectionProgressByTownExport implements FromCollection, WithHeadings, WithMapping, WithCharts
{
    private int $electionId;
    // Set by collection() so charts() knows the real cell range.
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

        $fpmRows = \DB::table('fpm_users')
            ->whereIn('MemberId', $permittedMemberIds)
            ->get(['MemberId', 'district', 'town'])
            ->keyBy('MemberId');

        $stats = [];
        foreach ($permittedMemberIds as $memberId) {
            $row = $fpmRows->get($memberId);
            $district = ($row && $row->district) ? $row->district : 'بدون دائرة';
            $town = ($row && $row->town) ? $row->town : 'بدون بلدة';
            $key = $district . '|' . $town;

            if (!isset($stats[$key])) {
                $stats[$key] = ['district' => $district, 'town' => $town, 'permitted' => 0, 'voted' => 0];
            }
            $stats[$key]['permitted']++;
            if ($votedSet->has($memberId)) {
                $stats[$key]['voted']++;
            }
        }

        $rows = collect($stats)->map(function ($s) {
            return (object) [
                'district' => $s['district'],
                'town' => $s['town'],
                'permitted' => $s['permitted'],
                'voted' => $s['voted'],
                'remaining' => $s['permitted'] - $s['voted'],
                'turnout' => $s['permitted'] > 0 ? round($s['voted'] / $s['permitted'] * 100, 1) : 0,
            ];
        })->sortBy(fn($r) => [$r->district, -$r->turnout])->values();

        $this->rowCount = $rows->count();

        return $rows;
    }

    public function charts()
    {
        if ($this->rowCount < 1) {
            return [];
        }

        $lastRow = $this->rowCount + 1; // +1 for the heading row

        // Categories use Town (column B) rather than District (column A) -
        // district repeats across many rows, town is the unique per-bar label.
        $labels = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, 'Worksheet!$F$1', null, 1)];
        $categories = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "Worksheet!\$B\$2:\$B\${$lastRow}", null, $this->rowCount)];
        $values = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, "Worksheet!\$F\$2:\$F\${$lastRow}", null, $this->rowCount)];

        // Show the turnout % value on top of each bar - see
        // InternalElectionProgressByDistrictExport for why automatic text
        // rotation isn't possible for the many-bars case.
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
        $title = new Title('Turnout % by Town');

        $chart = new Chart('turnout-by-town', $title, $legend, $plotArea);
        $chart->setTopLeftPosition('H2');
        $chart->setBottomRightPosition('R30');

        return $chart;
    }

    public function headings(): array
    {
        return ['District', 'Town', 'Permitted', 'Voted', 'Remaining', 'Turnout %'];
    }

    public function map($row): array
    {
        // Cast to string - see InternalElectionProgressByDistrictExport for
        // why: Laravel Excel's write pipeline collapses a literal numeric 0
        // to a NULL/blank cell otherwise.
        return [
            $row->district,
            $row->town,
            (string) $row->permitted,
            (string) $row->voted,
            (string) $row->remaining,
            (string) $row->turnout,
        ];
    }
}
