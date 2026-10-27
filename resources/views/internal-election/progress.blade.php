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
                        <p>اجمالي الناخبين في جميع الاقضية المشاركة</p>
                    </div>
                    <div class="icon"><i class="fa fa-users"></i></div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="small-box bg-green">
                    <div class="inner">
                        <h3>{{ number_format($totalVoted) }}</h3>
                        <p>عدد المصوتين في جميع الاقضية المشاركة</p>
                    </div>
                    <div class="icon"><i class="fa fa-check-circle"></i></div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="small-box bg-yellow">
                    <div class="inner">
                        <h3>{{ number_format($totalRemaining) }}</h3>
                        <p>عدد المتبقين في جميع الاقضية المشاركة</p>
                    </div>
                    <div class="icon"><i class="fa fa-clock-o"></i></div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="small-box bg-red">
                    <div class="inner">
                        <h3>{{ $overallTurnout }}%</h3>
                        <p>النسبة المئوية</p>
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
                        <h3 class="box-title">عدد الأصوات لكل ساعة</h3>
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
                        <h3 class="box-title">نسبة الاقتراع حسب القضاء</h3>
                        <div class="box-tools pull-right">
                            <a href="{{ route('admin.internal-election.progress.export-by-district', $election->id) }}" class="btn btn-success btn-sm">
                                <i class="fa fa-file-excel-o"></i> تصدير حسب القضاء
                            </a>
                            <a href="{{ route('admin.internal-election.progress.export-by-town', $election->id) }}" class="btn btn-success btn-sm">
                                <i class="fa fa-file-excel-o"></i> تصدير حسب البلدة
                            </a>
                        </div>
                        <p class="text-muted" style="margin: 5px 0 0;">مرتبة من الأعلى إلى الأدنى حسب نسبة الاقتراع.</p>
                    </div>
                    <div class="box-body">
                        @if($districtRows->isEmpty())
                            <div class="alert alert-warning" style="margin:0;">No permitted voters uploaded for this election yet.</div>
                        @else
                            <table class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>القضاء</th>
                                        <th>الناخبون</th>
                                        <th>المقترعون</th>
                                        <th>لم يقترعوا</th>
                                        <th>نسبة الاقتراع</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($districtRows as $row)
                                        <tr>
                                            <td>
                                                <a href="#" class="district-drilldown-link" data-district="{{ $row['district'] }}">{{ $row['district'] }}</a>
                                            </td>
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

<div class="modal fade" id="townDrilldownModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title" id="townDrilldownModalTitle">نسبة الاقتراع حسب البلدة</h4>
            </div>
            <div class="modal-body">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>البلدة</th>
                            <th>الناخبون</th>
                            <th>المقترعون</th>
                            <th>لم يقترعوا</th>
                            <th>نسبة الاقتراع</th>
                        </tr>
                    </thead>
                    <tbody id="townDrilldownModalBody">
                        <tr><td colspan="5" class="text-center text-muted">جارٍ التحميل...</td></tr>
                    </tbody>
                </table>
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

<script>
    $(function () {
        function turnoutBarColor(turnout) {
            if (turnout < 40) return 'progress-bar-danger';
            if (turnout < 70) return 'progress-bar-warning';
            return 'progress-bar-success';
        }

        $('body').on('click', '.district-drilldown-link', function (e) {
            e.preventDefault();
            var district = $(this).data('district');

            $('#townDrilldownModalTitle').text('نسبة الاقتراع حسب البلدة — ' + district);
            $('#townDrilldownModalBody').html('<tr><td colspan="5" class="text-center text-muted">Loading...</td></tr>');
            $('#townDrilldownModal').modal('show');

            $.get('{{ route('admin.internal-election.progress.towns', $election->id) }}', { district: district })
                .done(function (response) {
                    var $body = $('#townDrilldownModalBody').empty();

                    if (!response.towns || !response.towns.length) {
                        $body.append('<tr><td colspan="5" class="text-center text-muted">لا يوجد ناخبون مسموح لهم في هذا القضاء.</td></tr>');
                        return;
                    }

                    response.towns.forEach(function (row) {
                        var $tr = $('<tr>');
                        $tr.append($('<td>').text(row.town));
                        $tr.append($('<td>').text(row.permitted));
                        $tr.append($('<td>').text(row.voted));
                        $tr.append($('<td>').text(row.remaining));

                        var $turnoutCell = $('<td>').css({ 'min-width': '220px' });
                        var $wrap = $('<div>').css({ display: 'flex', 'align-items': 'center', gap: '8px' });
                        var $track = $('<div>').addClass('progress election-progress-track').css({ 'margin-bottom': 0, flex: 1 });
                        var $bar = $('<div>').addClass('progress-bar ' + turnoutBarColor(row.turnout))
                            .attr('role', 'progressbar').css('width', row.turnout + '%');
                        $track.append($bar);
                        var $label = $('<span>').css({ 'white-space': 'nowrap', 'font-weight': 'bold' }).text(row.turnout + '%');
                        $wrap.append($track).append($label);
                        $turnoutCell.append($wrap);
                        $tr.append($turnoutCell);

                        $body.append($tr);
                    });
                })
                .fail(function (xhr) {
                    console.error('Town drilldown failed', xhr.status, xhr.responseText);
                    $('#townDrilldownModalBody').html('<tr><td colspan="5" class="text-center text-danger">Failed to load — see console for details.</td></tr>');
                });
        });
    });
</script>
@endsection
