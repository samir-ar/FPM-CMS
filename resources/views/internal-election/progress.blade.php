@extends('layouts.cms')

@section('header_resources')
<style>
    /* Scoped to this page only - makes the track itself visible even at
       0%, instead of relying on AdminLTE's default near-invisible light
       grey (which looked like there was no bar at all). */
    .election-progress-track {
        background-color: #e4e4e4;
        border: 1px solid #c9c9c9;
    }
</style>
@endsection

@section('content')
<div class="box box-default">
    <div class="box-header with-border">
        <h3 class="box-title">{{ $election->title }} — Election Progress</h3>
    </div>
    <div class="box-body">
        <div class="row">
            <div class="col-md-3 col-sm-6">
                <div class="small-box bg-aqua">
                    <div class="inner">
                        <h3>{{ number_format($totalPermitted) }}</h3>
                        <p>Permitted Voters</p>
                    </div>
                    <div class="icon"><i class="fa fa-users"></i></div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="small-box bg-green">
                    <div class="inner">
                        <h3>{{ number_format($totalVoted) }}</h3>
                        <p>Voted</p>
                    </div>
                    <div class="icon"><i class="fa fa-check-circle"></i></div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="small-box bg-yellow">
                    <div class="inner">
                        <h3>{{ number_format($totalRemaining) }}</h3>
                        <p>Remaining</p>
                    </div>
                    <div class="icon"><i class="fa fa-clock-o"></i></div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="small-box bg-red">
                    <div class="inner">
                        <h3>{{ $overallTurnout }}%</h3>
                        <p>Overall Turnout</p>
                    </div>
                    <div class="icon"><i class="fa fa-pie-chart"></i></div>
                </div>
            </div>
        </div>

        <div style="display:flex; align-items:center; gap:10px;">
            <div class="progress election-progress-track" style="height: 25px; margin-bottom: 0; flex: 1;">
                <div class="progress-bar progress-bar-success" role="progressbar" style="width: {{ $overallTurnout }}%;">
                </div>
            </div>
            <span style="white-space:nowrap; font-weight:bold; font-size:16px;">{{ $overallTurnout }}%</span>
        </div>

        <div class="row" style="margin-top: 20px;">
            <div class="col-md-12">
                <div class="box box-default">
                    <div class="box-header with-border">
                        <h3 class="box-title">Votes Per Hour</h3>
                    </div>
                    <div class="box-body">
                        @if($hourly->isEmpty())
                            <div class="alert alert-warning" style="margin:0;">No votes recorded yet for this election.</div>
                        @else
                            <div id="hourly-votes-chart" style="height: 250px;"></div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <div class="box box-default">
                    <div class="box-header with-border">
                        <h3 class="box-title">Turnout By District</h3>
                        <p class="text-muted" style="margin: 5px 0 0;">Sorted lowest turnout first — these districts likely need a reminder push.</p>
                    </div>
                    <div class="box-body">
                        @if($districtRows->isEmpty())
                            <div class="alert alert-warning" style="margin:0;">No permitted voters uploaded for this election yet.</div>
                        @else
                            <table class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>District</th>
                                        <th>Permitted</th>
                                        <th>Voted</th>
                                        <th>Remaining</th>
                                        <th>Turnout</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($districtRows as $row)
                                        <tr>
                                            <td>{{ $row['district'] }}</td>
                                            <td>{{ number_format($row['permitted']) }}</td>
                                            <td>{{ number_format($row['voted']) }}</td>
                                            <td>{{ number_format($row['remaining']) }}</td>
                                            <td style="min-width: 220px;">
                                                <div style="display:flex; align-items:center; gap:8px;">
                                                    <div class="progress election-progress-track" style="margin-bottom: 0; flex: 1;">
                                                        <div class="progress-bar progress-bar-{{ $row['turnout'] < 40 ? 'danger' : ($row['turnout'] < 70 ? 'warning' : 'success') }}"
                                                             role="progressbar" style="width: {{ $row['turnout'] }}%;">
                                                        </div>
                                                    </div>
                                                    <span style="white-space:nowrap; font-weight:bold;">{{ $row['turnout'] }}%</span>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('footer_resources')
@if($hourly->isNotEmpty())
<script>
    $(function () {
        Morris.Bar({
            element: 'hourly-votes-chart',
            data: @json($hourly->map(fn($count, $hour) => ['hour' => $hour, 'votes' => $count])->values()),
            xkey: 'hour',
            ykeys: ['votes'],
            labels: ['Votes'],
            barColors: ['#00a65a'],
            resize: true,
            parseTime: false,
        });
    });
</script>
@endif
@endsection
