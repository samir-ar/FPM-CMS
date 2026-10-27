@extends('layouts.cms')

@section('content')
<div class="alert alert-info">
    <strong>Note:</strong> Activity tracking started on {{ \Carbon\Carbon::parse($trackingStartedAt)->format('F j, Y') }}.
    Numbers only reflect real usage from that date forward — there is no way to reconstruct activity from before it.
    Accuracy (especially the "dormant" list) improves the longer this runs.
</div>

<div class="row">
    <div class="col-xs-12">
        <div style="display:flex; flex-wrap:nowrap; gap:10px;">
            <div style="flex:1 1 0; min-width:0;">
                <div class="small-box bg-aqua" style="cursor:pointer" onclick="openActivityModal('today', 'Active Today')">
                    <div class="inner">
                        <h3>{{ number_format($dau) }}</h3>
                        <p>Active Today</p>
                    </div>
                    <div class="icon"><i class="fa fa-bolt"></i></div>
                </div>
            </div>
            <div style="flex:1 1 0; min-width:0;">
                <div class="small-box bg-green" style="cursor:pointer" onclick="openActivityModal('week', 'Active This Week')">
                    <div class="inner">
                        <h3>{{ number_format($wau) }}</h3>
                        <p>Active This Week</p>
                    </div>
                    <div class="icon"><i class="fa fa-calendar-check-o"></i></div>
                </div>
            </div>
            <div style="flex:1 1 0; min-width:0;">
                <div class="small-box bg-yellow" style="cursor:pointer" onclick="openActivityModal('month', 'Active This Month')">
                    <div class="inner">
                        <h3>{{ number_format($mau) }}</h3>
                        <p>Active This Month</p>
                    </div>
                    <div class="icon"><i class="fa fa-calendar"></i></div>
                </div>
            </div>
            <div style="flex:1 1 0; min-width:0;">
                <div class="small-box bg-red" style="cursor:pointer" onclick="openActivityModal('year', 'Active This Year')">
                    <div class="inner">
                        <h3>{{ number_format($yau) }}</h3>
                        <p>Active This Year</p>
                    </div>
                    <div class="icon"><i class="fa fa-calendar-o"></i></div>
                </div>
            </div>
            <div style="flex:1 1 0; min-width:0;">
                <div class="small-box bg-purple" style="cursor:pointer" onclick="openActivityModal('decade', 'Active (Last 10 Years)')">
                    <div class="inner">
                        <h3>{{ number_format($tenYearActive) }}</h3>
                        <p>Active (Last 10 Years)</p>
                    </div>
                    <div class="icon"><i class="fa fa-history"></i></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="activityMembersModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title" id="activityMembersModalTitle">Active Members</h4>
            </div>
            <div class="modal-body">
                <div class="row" style="margin-bottom: 15px;">
                    <div class="col-sm-6">
                        <select id="activityDistrictFilter" class="form-control">
                            <option value="">All districts</option>
                        </select>
                    </div>
                    <div class="col-sm-6">
                        <select id="activityTownFilter" class="form-control">
                            <option value="">All towns</option>
                        </select>
                    </div>
                </div>
                <div style="max-height: 55vh; overflow-y: auto;">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Member ID</th>
                                <th>Name</th>
                                <th>District</th>
                                <th>Town</th>
                                <th>Last Activity</th>
                            </tr>
                        </thead>
                        <tbody id="activityMembersModalBody">
                            <tr><td colspan="5" class="text-center text-muted">Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
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
    var activityModalMembers = [];

    function escapeHtml(s) {
        return $('<div>').text(s == null ? '' : s).html();
    }

    function renderActivityRows(members) {
        if (!members.length) {
            $('#activityMembersModalBody').html('<tr><td colspan="5" class="text-center text-muted">No members found.</td></tr>');
            return;
        }
        var rows = members.map(function (m) {
            return '<tr>' +
                '<td>' + escapeHtml(m.member_id || '—') + '</td>' +
                '<td>' + escapeHtml(m.name || '—') + '</td>' +
                '<td>' + escapeHtml(m.district || '—') + '</td>' +
                '<td>' + escapeHtml(m.town || '—') + '</td>' +
                '<td>' + escapeHtml(m.last_active || '—') + '</td>' +
                '</tr>';
        }).join('');
        $('#activityMembersModalBody').html(rows);
    }

    function applyActivityFilters() {
        var district = $('#activityDistrictFilter').val();
        var town = $('#activityTownFilter').val();
        var filtered = activityModalMembers.filter(function (m) {
            return (!district || m.district === district) && (!town || m.town === town);
        });
        renderActivityRows(filtered);
    }

    function populateActivityDistrictOptions() {
        var districts = activityModalMembers
            .map(function (m) { return m.district; })
            .filter(function (d, i, arr) { return d && arr.indexOf(d) === i; })
            .sort();
        var options = '<option value="">All districts</option>';
        districts.forEach(function (d) { options += '<option value="' + escapeHtml(d) + '">' + escapeHtml(d) + '</option>'; });
        $('#activityDistrictFilter').html(options);
    }

    function populateActivityTownOptions(district) {
        var towns = activityModalMembers
            .filter(function (m) { return !district || m.district === district; })
            .map(function (m) { return m.town; })
            .filter(function (t, i, arr) { return t && arr.indexOf(t) === i; })
            .sort();
        var options = '<option value="">All towns</option>';
        towns.forEach(function (t) { options += '<option value="' + escapeHtml(t) + '">' + escapeHtml(t) + '</option>'; });
        $('#activityTownFilter').html(options);
    }

    $('#activityDistrictFilter').on('change', function () {
        populateActivityTownOptions($(this).val());
        applyActivityFilters();
    });
    $('#activityTownFilter').on('change', applyActivityFilters);

    function openActivityModal(period, title) {
        $('#activityMembersModalTitle').text(title);
        $('#activityMembersModalBody').html('<tr><td colspan="5" class="text-center text-muted">Loading...</td></tr>');
        $('#activityDistrictFilter').html('<option value="">All districts</option>');
        $('#activityTownFilter').html('<option value="">All towns</option>');
        $('#activityMembersModal').modal('show');

        $.getJSON('{{ url('admin/user-activity/members') }}/' + period, function (members) {
            activityModalMembers = members;
            populateActivityDistrictOptions();
            populateActivityTownOptions('');
            renderActivityRows(members);
        }).fail(function () {
            $('#activityMembersModalBody').html('<tr><td colspan="5" class="text-center text-muted">Failed to load.</td></tr>');
        });
    }

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
            parseTime: false,
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
            parseTime: false,
        });
    });
</script>
@endsection
