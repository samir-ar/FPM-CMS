@extends('layouts.cms')

@section('content')
<div class="alert alert-info">
    <strong>Note:</strong> Activity tracking started on {{ \Carbon\Carbon::parse($trackingStartedAt)->format('F j, Y') }}.
    Numbers only reflect real usage from that date forward — there is no way to reconstruct activity from before it.
    Accuracy (especially the "dormant" list) improves the longer this runs.
</div>

<div class="row">
    <div class="col-md-3 col-sm-6">
        <div class="small-box bg-aqua">
            <div class="inner">
                <h3>{{ number_format($dau) }}</h3>
                <p>Active Today</p>
            </div>
            <div class="icon"><i class="fa fa-bolt"></i></div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="small-box bg-green">
            <div class="inner">
                <h3>{{ number_format($wau) }}</h3>
                <p>Active This Week</p>
            </div>
            <div class="icon"><i class="fa fa-calendar-check-o"></i></div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="small-box bg-yellow">
            <div class="inner">
                <h3>{{ number_format($mau) }}</h3>
                <p>Active This Month</p>
            </div>
            <div class="icon"><i class="fa fa-calendar"></i></div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="small-box bg-red">
            <div class="inner">
                <h3>{{ number_format($yau) }}</h3>
                <p>Active This Year</p>
            </div>
            <div class="icon"><i class="fa fa-calendar-o"></i></div>
        </div>
    </div>
</div>

<div class="box box-default">
    <div class="box-header with-border">
        <h3 class="box-title">Daily Active Members — Last 30 Days</h3>
    </div>
    <div class="box-body">
        <div id="activity-trend-chart" style="height: 260px;"></div>
    </div>
</div>

<div class="box box-default">
    <div class="box-header with-border">
        <h3 class="box-title">Periodically Active Members — Rolling 30-Day Window</h3>
    </div>
    <div class="box-body">
        <p class="text-muted">
            Counts anyone active at <strong>any point in the trailing 30 days</strong>, not just that exact
            day — so a member who opens the app every couple of weeks shows up as active throughout their
            normal rhythm, instead of looking "gone" on the days in between.
        </p>
        <div id="periodic-trend-chart" style="height: 260px;"></div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="box box-default">
            <div class="box-header with-border">
                <h3 class="box-title">Engagement Frequency — Last 30 Days</h3>
            </div>
            <div class="box-body">
                <table class="table">
                    <tr>
                        <td>Power users (active 20+ of last 30 days)</td>
                        <td class="text-right"><strong>{{ number_format($powerUsers) }}</strong></td>
                    </tr>
                    <tr>
                        <td>Regular users (active 5–19 of last 30 days)</td>
                        <td class="text-right"><strong>{{ number_format($regularUsers) }}</strong></td>
                    </tr>
                    <tr>
                        <td>Casual users (active 1–4 of last 30 days)</td>
                        <td class="text-right"><strong>{{ number_format($casualUsers) }}</strong></td>
                    </tr>
                    <tr>
                        <td>Total registered members</td>
                        <td class="text-right"><strong>{{ number_format($totalMembers) }}</strong></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="box box-default">
            <div class="box-header with-border">
                <h3 class="box-title">Most Engaged Members</h3>
            </div>
            <div class="box-body" style="max-height: 320px; overflow-y: auto;">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Member ID</th>
                            <th>Days Active</th>
                            <th>Last Active</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($mostEngaged as $m)
                        <tr>
                            <td>{{ $m->name }}</td>
                            <td>{{ $m->member_id }}</td>
                            <td>{{ $m->days_active }}</td>
                            <td>{{ $m->last_active }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="4" class="text-center text-muted">No activity recorded yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="box box-default">
    <div class="box-header with-border">
        <h3 class="box-title">Dormant Members (no activity in 30+ days, or never)</h3>
    </div>
    <div class="box-body" style="max-height: 400px; overflow-y: auto;">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Member ID</th>
                    <th>Last Active</th>
                </tr>
            </thead>
            <tbody>
                @forelse($dormant as $d)
                <tr>
                    <td>{{ $d->name }}</td>
                    <td>{{ $d->member_id }}</td>
                    <td>{{ $d->last_active ?? 'Never (since tracking began)' }}</td>
                </tr>
                @empty
                <tr><td colspan="3" class="text-center text-muted">No dormant members found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

@section('footer_resources')
<script>
    $(function () {
        Morris.Line({
            element: 'activity-trend-chart',
            data: @json(collect($trendLabels)->map(fn($label, $i) => ['day' => $label, 'active' => $trendValues[$i]])),
            xkey: 'day',
            ykeys: ['active'],
            labels: ['Active Members'],
            lineColors: ['#00c0ef'],
            resize: true,
            smooth: false,
        });

        Morris.Line({
            element: 'periodic-trend-chart',
            data: @json(collect($periodicLabels)->map(fn($label, $i) => ['day' => $label, 'active' => $periodicValues[$i]])),
            xkey: 'day',
            ykeys: ['active'],
            labels: ['Periodically Active Members'],
            lineColors: ['#00a65a'],
            resize: true,
            smooth: false,
        });
    });
</script>
@endsection
