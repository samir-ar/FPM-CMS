@extends('layouts.cms')

@section('content')
<div class="alert alert-info">
    <strong>Note:</strong> Android and iOS expose genuinely different data through their respective APIs —
    Google doesn't provide impressions or store-listing conversion rate programmatically at all, while Apple
    doesn't provide a simple "current active install base" number. Each platform below shows what's actually
    available for it rather than forcing artificial parity. Figures are cached for up to an hour.
</div>

<h3>Android <small>(Google Play)</small></h3>

@if(!$summary['android']['available'])
    <div class="alert alert-warning">
        Android data is temporarily unavailable: {{ $summary['android']['error'] ?? 'unknown error' }}
    </div>
@else
    @php $android = $summary['android']; @endphp

    @if($android['latest_data_date'])
        <p class="text-muted">Latest install data as of <strong>{{ \Carbon\Carbon::parse($android['latest_data_date'])->format('F j, Y') }}</strong> (Google publishes these monthly stats files with a few days' delay).</p>
    @endif

    <div class="row">
        <div class="col-md-3 col-sm-6">
            <div class="small-box bg-aqua">
                <div class="inner">
                    <h3>{{ $android['active_installs'] !== null ? number_format($android['active_installs']) : '—' }}</h3>
                    <p>Active Installs</p>
                </div>
                <div class="icon"><i class="fa fa-mobile"></i></div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="small-box bg-green">
                <div class="inner">
                    <h3>{{ number_format($android['installs_30d']) }}</h3>
                    <p>Installs (30d)</p>
                </div>
                <div class="icon"><i class="fa fa-download"></i></div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="small-box bg-yellow">
                <div class="inner">
                    <h3>{{ number_format($android['uninstalls_30d']) }}</h3>
                    <p>Uninstalls (30d)</p>
                </div>
                <div class="icon"><i class="fa fa-times-circle"></i></div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="small-box bg-red">
                <div class="inner">
                    <h3>{{ $android['crash_rate_7d_avg'] !== null ? number_format($android['crash_rate_7d_avg'] * 100, 2) . '%' : '—' }}</h3>
                    <p>Crash Rate (7d avg)</p>
                </div>
                <div class="icon"><i class="fa fa-bug"></i></div>
            </div>
        </div>
    </div>

    <div class="box box-default">
        <div class="box-header with-border">
            <h3 class="box-title">Daily Installs vs Uninstalls</h3>
        </div>
        <div class="box-body">
            <div id="android-trend-chart" style="height: 260px;"></div>
        </div>
    </div>
@endif

<h3>iOS <small>(App Store)</small></h3>

@if(!$summary['ios']['available'])
    @if($summary['ios']['pending_first_report'] ?? false)
        <div class="alert alert-info">
            iOS analytics reporting was just set up — Apple takes up to 24–48 hours to generate the first
            daily report after an analytics report request is created. This section will populate
            automatically once that first report lands; no action needed.
        </div>
    @else
        <div class="alert alert-warning">
            iOS data is temporarily unavailable: {{ $summary['ios']['error'] ?? 'unknown error' }}
        </div>
    @endif
@else
    @php $ios = $summary['ios']; @endphp

    @if($ios['downloads'])
        <div class="box box-default">
            <div class="box-header with-border">
                <h3 class="box-title">App Store Installation and Deletion — {{ \Carbon\Carbon::parse($ios['downloads']['processing_date'])->format('F j, Y') }}</h3>
            </div>
            <div class="box-body" style="max-height: 400px; overflow-y: auto;">
                @include('cms.store_analytics._raw_table', ['rows' => $ios['downloads']['rows']])
            </div>
        </div>
    @endif

    @if($ios['engagement'])
        <div class="box box-default">
            <div class="box-header with-border">
                <h3 class="box-title">App Store Discovery and Engagement — {{ \Carbon\Carbon::parse($ios['engagement']['processing_date'])->format('F j, Y') }}</h3>
            </div>
            <div class="box-body" style="max-height: 400px; overflow-y: auto;">
                @include('cms.store_analytics._raw_table', ['rows' => $ios['engagement']['rows']])
            </div>
        </div>
    @endif
@endif

<p class="text-muted small">Generated at {{ $summary['generated_at'] }}</p>
@endsection

@section('footer_resources')
<script>
    $(function () {
        @if($summary['android']['available'])
        Morris.Line({
            element: 'android-trend-chart',
            data: @json($summary['android']['daily_trend']),
            xkey: 'date',
            ykeys: ['installs', 'uninstalls'],
            labels: ['Installs', 'Uninstalls'],
            lineColors: ['#00a65a', '#dd4b39'],
            resize: true,
            smooth: false,
        });
        @endif
    });
</script>
@endsection
