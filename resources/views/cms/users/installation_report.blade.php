@extends($layout)

@section('header_resources')
<style>
    .ir-filters .form-group { margin-right: 10px; margin-bottom: 8px; }
    .ir-table th { background: #FFC000; text-align: center; white-space: nowrap; }
    .ir-table td, .ir-table th { border: 1px solid #999 !important; }
    .ir-table td.num { text-align: center; }
    .ir-table tr.total td { font-weight: bold; text-align: center; }
    .ir-table .sorted { background: #FFE699; }
    .ir-wrap { overflow-x: auto; margin-bottom: 25px; }
</style>
@endsection

@section('content')
@php
    $query = array_merge(['year' => $picked->year, 'month' => $picked->month], array_filter($filters, fn($v) => $v !== ''));
    $columns = [
        ['title' => 'Table 1 - Total App installed per casaa', 'rows' => $byInstalled, 'sort' => 'total_installed'],
        ['title' => 'Table 2 - Total ID FPM per casaa (members)', 'rows' => $byMember, 'sort' => 'total_member'],
        ['title' => 'Table 3 - Percentage installed / members', 'rows' => $byPercentage, 'sort' => 'percentage'],
    ];
    $pct = $totals['member'] > 0 ? round($totals['installed'] / $totals['member'] * 100, 2) : 0;
    $activePct = $totals['member'] > 0 ? round($totals['active'] / $totals['member'] * 100, 2) : 0;
@endphp

<div class="box box-default">
    <div class="box-header with-border">
        <h3 class="box-title">Installation Report - {{ $picked->format('F Y') }}</h3>
        <a href="{{ route('admin.users.index') }}" class="btn btn-default pull-right" style="margin-left:4px;">Back to App Users</a>
        <a href="{{ route('admin.users.installation-report.download', $query) }}" class="btn btn-success pull-right">Download Excel</a>
    </div>
    <div class="box-body">
        <form method="GET" action="{{ route('admin.users.installation-report') }}" class="form-inline ir-filters">
            <div class="form-group">
                <label>Month</label>
                <select name="month" class="form-control">
                    @foreach(range(1, 12) as $m)
                        <option value="{{ $m }}" @selected($picked->month === $m)>{{ \Carbon\Carbon::create(2000, $m, 1)->format('F') }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>Year</label>
                <select name="year" class="form-control">
                    @foreach(collect($months)->pluck('year')->unique() as $y)
                        <option value="{{ $y }}" @selected($picked->year === $y)>{{ $y }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>District</label>
                <select name="district" class="form-control">
                    <option value="">All districts</option>
                    @foreach($districts as $d)
                        <option value="{{ $d }}" @selected($filters['district'] === $d)>{{ $d }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group"><label>Town</label> <input type="text" name="town" class="form-control" value="{{ $filters['town'] }}" placeholder="contains..."></div>
            <div class="form-group"><label>Sect</label> <input type="text" name="sect" class="form-control" value="{{ $filters['sect'] }}" placeholder="contains..."></div>
            <div class="form-group"><label>Activity Unit</label> <input type="text" name="unit" class="form-control" value="{{ $filters['unit'] }}" placeholder="contains..."></div>
            <div class="form-group"><label>Member ID</label> <input type="text" name="member_id" class="form-control" value="{{ $filters['member_id'] }}" placeholder="exact"></div>
            <button type="submit" class="btn btn-primary" style="margin-bottom:8px;">Generate</button>
            <a href="{{ route('admin.users.installation-report') }}" class="btn btn-default" style="margin-bottom:8px;">Reset</a>
        </form>

        <p class="text-muted">
            <b>Installed</b> = members with a verified app account created by the end of {{ $picked->format('F Y') }}.
            <b>Active</b> = members who opened the app at least one day in {{ $picked->format('F Y') }} (app activity is recorded from {{ $trackingStart }}, so September counts from the 24th).
            Percentages are out of the members that match the filters.
        </p>

        <p>
            <b>Total members:</b> {{ number_format($totals['member']) }} &nbsp;|&nbsp;
            <b>Installed:</b> {{ number_format($totals['installed']) }} ({{ $pct }}%) &nbsp;|&nbsp;
            <b>Active in {{ $picked->format('F Y') }}:</b> {{ number_format($totals['active']) }} ({{ $activePct }}%)
        </p>

        @foreach($columns as $t)
            <h4>{{ $t['title'] }}</h4>
            <div class="ir-wrap">
                <table class="table table-condensed ir-table">
                    <thead>
                        <tr>
                            <th>Kada2</th>
                            <th>total_installed</th>
                            <th>total_member</th>
                            <th>total_installed_percentage</th>
                            <th>total_active</th>
                            <th>total_active_percentage</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($t['rows'] as $r)
                            <tr>
                                <td>{{ $r['kada'] }}</td>
                                <td class="num {{ $t['sort'] === 'total_installed' ? 'sorted' : '' }}">{{ $r['total_installed'] }}</td>
                                <td class="num {{ $t['sort'] === 'total_member' ? 'sorted' : '' }}">{{ $r['total_member'] }}</td>
                                <td class="num {{ $t['sort'] === 'percentage' ? 'sorted' : '' }}">{{ $r['percentage'] }}</td>
                                <td class="num">{{ $r['total_active'] }}</td>
                                <td class="num">{{ $r['active_percentage'] }}</td>
                            </tr>
                        @endforeach
                        <tr class="total">
                            <td>TOTAL</td>
                            <td>{{ $totals['installed'] }}</td>
                            <td>{{ $totals['member'] }}</td>
                            <td>{{ $pct }}</td>
                            <td>{{ $totals['active'] }}</td>
                            <td>{{ $activePct }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        @endforeach
    </div>
</div>
@endsection
