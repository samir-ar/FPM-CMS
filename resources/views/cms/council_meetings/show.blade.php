@extends($layout)

@section('footer_resources')
<script>
    $(function () {
        var csrf = '{{ csrf_token() }}';

        function flash(ok, text) {
            $('#inlineMessage').removeClass('alert-success alert-danger').addClass(ok ? 'alert-success' : 'alert-danger').text(text).show();
            setTimeout(function () { $('#inlineMessage').fadeOut(); }, 4000);
        }

        // ---- live quorum numbers ----
        function paint(s) {
            $('#sPresentCount').text(s.present_count);
            $('#sPresentWeight').text(s.present_weight);
            $('#sMajority').toggleClass('text-success', s.majority_reached).toggleClass('text-danger', !s.majority_reached)
                .text(s.majority_reached ? '✔ quorum reached' : s.needed_majority + ' more needed');
            $('#sTwoThirds').toggleClass('text-success', s.two_thirds_reached).toggleClass('text-danger', !s.two_thirds_reached)
                .text(s.two_thirds_reached ? '✔ quorum reached' : s.needed_two_thirds + ' more needed');
            var pct = s.total_weight ? Math.min(100, Math.round(s.present_weight * 100 / s.total_weight)) : 0;
            $('#sBar').css('width', pct + '%').text(pct + '%');
        }
        setInterval(function () {
            $.get("{{ route('admin.council-meetings.stats', $meeting->id) }}", paint);
        }, 10000);

        @if($canAdd)
        // ---- search + add (restricted to the roster) ----
        var searchTimeout;
        $('#memberSearchInput').on('input', function () {
            clearTimeout(searchTimeout);
            var q = $(this).val();
            $('#selectedMemberId').val('');
            if (q.length < 2) { $('#memberSearchResults').hide().empty(); return; }
            searchTimeout = setTimeout(function () {
                $.get("{{ route('admin.council-meetings.search', $meeting->id) }}", { q: q }, function (data) {
                    var html = data.length ? '' : '<div class="list-group-item">No results among the council members</div>';
                    data.forEach(function (m) {
                        html += '<a href="#" class="list-group-item member-result" data-id="' + m.member_id + '">' +
                            (m.name || '') + ' — ' + (m.role || '') + ' — weight ' + (m.weight == null ? '?' : m.weight) +
                            ' — #' + m.member_id + (m.already_present ? ' (present)' : '') + '</a>';
                    });
                    $('#memberSearchResults').html(html).show();
                });
            }, 300);
        });
        $(document).on('click', '.member-result', function (e) {
            e.preventDefault();
            $('#selectedMemberId').val($(this).data('id'));
            $('#memberSearchInput').val($(this).text());
            $('#memberSearchResults').hide().empty();
        });
        $(document).on('click', function (e) {
            if (!$(e.target).closest('#memberSearchWrapper').length) { $('#memberSearchResults').hide(); }
        });

        function addAttendance(memberId) {
            $.post("{{ route('admin.council-meetings.attendance.store', $meeting->id) }}", { _token: csrf, member_id: memberId })
                .done(function (r) { flash(true, r.message); setTimeout(function () { location.reload(); }, 700); })
                .fail(function (xhr) { flash(false, (xhr.responseJSON && xhr.responseJSON.message) || 'Something went wrong'); });
        }
        $('#addAttendanceForm').on('submit', function (e) {
            e.preventDefault();
            var id = $('#selectedMemberId').val();
            if (!id) { alert('Please pick a member from the list first'); return; }
            addAttendance(id);
        });
        $(document).on('click', '.mark-present', function () { addAttendance($(this).data('id')); });
        @endif

        @if($canManage)
        $(document).on('click', '.remove-attendance', function () {
            if (!confirm('Cancel the attendance of ' + $(this).data('name') + '?')) return;
            $.ajax({ url: $(this).data('url'), type: 'DELETE', data: { _token: csrf } })
                .done(function () { location.reload(); })
                .fail(function () { flash(false, 'Something went wrong'); });
        });
        @endif
    });
</script>
@endsection

@section('content')
<div class="box box-default">
    <div class="box-header with-border">
        <h3 class="box-title" dir="auto">{{ $meeting->title }} @if(!$meeting->is_active) <span class="label label-warning">Closed</span> @endif</h3>
        <a href="{{ route('admin.council-meetings.index') }}" class="btn btn-default pull-right" style="margin-left:4px;">Back</a>
        <a href="{{ route('admin.council-meetings.board', $meeting->id) }}" target="_blank" class="btn btn-primary pull-right" style="margin-left:4px;"><i class="fa fa-television"></i> Live display</a>
        @if($canExport)
            <a href="{{ route('admin.council-meetings.attendance.export', $meeting->id) }}" class="btn btn-success pull-right" style="margin-left:4px;">Export attendance</a>
        @endif
        @if($canManage)
            <a href="{{ route('admin.council-meetings.toggle-active', $meeting->id) }}" class="btn btn-warning pull-right" style="margin-left:4px;">{{ $meeting->is_active ? 'Close meeting' : 'Open meeting' }}</a>
        @endif
    </div>
    <div class="box-body">
        <div id="inlineMessage" class="alert" style="display:none;"></div>

        {{-- live quorum --}}
        <div class="row" style="margin-bottom:10px;">
            <div class="col-md-3"><strong>Present:</strong> <span id="sPresentCount">{{ $stats['present_count'] }}</span> / {{ $stats['member_count'] }}</div>
            <div class="col-md-3"><strong>Present weight:</strong> <span id="sPresentWeight">{{ $stats['present_weight'] }}</span> / {{ $stats['total_weight'] }}</div>
            <div class="col-md-3"><strong>Half + 1 ({{ $stats['quorum_majority'] }}):</strong>
                <span id="sMajority" class="{{ $stats['majority_reached'] ? 'text-success' : 'text-danger' }}">{{ $stats['majority_reached'] ? '✔ quorum reached' : $stats['needed_majority'] . ' more needed' }}</span></div>
            <div class="col-md-3"><strong>Two-thirds ({{ $stats['quorum_two_thirds'] }}):</strong>
                <span id="sTwoThirds" class="{{ $stats['two_thirds_reached'] ? 'text-success' : 'text-danger' }}">{{ $stats['two_thirds_reached'] ? '✔ quorum reached' : $stats['needed_two_thirds'] . ' more needed' }}</span></div>
        </div>
        <div class="progress" style="height:22px;">
            @php($pct = $stats['total_weight'] ? min(100, round($stats['present_weight'] * 100 / $stats['total_weight'])) : 0)
            <div id="sBar" class="progress-bar progress-bar-success" style="width: {{ $pct }}%; line-height:22px;">{{ $pct }}%</div>
        </div>

        @if($rosterTotalNow != $stats['total_weight'] || $rosterCountNow != $stats['member_count'])
            <div class="alert alert-warning">
                The council roster has changed since this meeting was created (now {{ $rosterCountNow }} members, total weight {{ $rosterTotalNow }}).
                This meeting's quorum is still frozen at {{ $stats['member_count'] }} members / {{ $stats['total_weight'] }}.
                @if($canManage)
                    <a href="{{ route('admin.council-meetings.refresh-quorum', $meeting->id) }}" onclick="return confirm('Update this meeting\'s quorum from the current roster?')">Refresh quorum</a>
                @endif
            </div>
        @endif

        @if($canAdd && $meeting->is_active)
        <h4>Record attendance manually</h4>
        <form id="addAttendanceForm" style="margin-bottom:20px;">
            <div style="display:flex; gap:8px; align-items:flex-start;">
                <div id="memberSearchWrapper" style="position:relative; width:460px;">
                    <input type="text" id="memberSearchInput" class="form-control" autocomplete="off"
                           placeholder="Search by name, phone or member ID (council members only)">
                    <div id="memberSearchResults" class="list-group"
                         style="display:none; position:absolute; z-index:1000; width:100%; max-height:260px; overflow-y:auto; margin-top:2px;"></div>
                </div>
                <input type="hidden" id="selectedMemberId">
                <button type="submit" class="btn btn-primary">Mark present</button>
            </div>
        </form>
        @endif

        <h4>Present ({{ $present->count() }})</h4>
        <table class="table table-bordered table-striped">
            <thead>
                <tr><th>#</th><th>Member ID</th><th>Name</th><th>Role</th><th>Weight</th><th>Time</th><th>Method</th>@if($canManage)<th></th>@endif</tr>
            </thead>
            <tbody>
            @forelse($present as $i => $a)
                @php($d = $details[$a->member_id] ?? null)
                <tr>
                    <td>{{ $present->count() - $i }}</td>
                    <td>{{ $a->member_id }}</td>
                    <td dir="auto">{{ $d->UserFullName ?? '' }}</td>
                    <td dir="auto">{{ $roster[$a->member_id]->role ?? '' }}</td>
                    <td>{{ $a->weight }}</td>
                    <td>{{ optional($a->checked_in_at)->format('H:i:s') }}</td>
                    <td>{{ $a->scanned_by_app_user_id ? 'QR scan' : 'Manual' }}</td>
                    @if($canManage)
                    <td><button class="btn btn-xs btn-danger remove-attendance" data-name="{{ $d->UserFullName ?? $a->member_id }}"
                                data-url="{{ route('admin.council-meetings.attendance.destroy', [$meeting->id, $a->id]) }}">Cancel</button></td>
                    @endif
                </tr>
            @empty
                <tr><td colspan="8" class="text-center">Nobody has been marked present yet.</td></tr>
            @endforelse
            </tbody>
        </table>

        <h4 style="margin-top:30px;">Absent ({{ $absent->count() }})</h4>
        <table class="table table-bordered table-striped">
            <thead><tr><th>Member ID</th><th>Name</th><th>Phone</th><th>Role</th><th>Weight</th>@if($canAdd && $meeting->is_active)<th></th>@endif</tr></thead>
            <tbody>
            @foreach($absent as $m)
                @php($d = $details[$m->member_id] ?? null)
                <tr>
                    <td>{{ $m->member_id }}</td>
                    <td dir="auto">{{ $d->UserFullName ?? '' }}</td>
                    <td>{{ $d->MobileNumber ?? '' }}</td>
                    <td dir="auto">{{ $m->role }}</td>
                    <td>{{ $m->weight }}</td>
                    @if($canAdd && $meeting->is_active)
                    <td><button class="btn btn-xs btn-success mark-present" data-id="{{ $m->member_id }}">Present</button></td>
                    @endif
                </tr>
            @endforeach
            </tbody>
        </table>

        <h4 style="margin-top:30px;">App login readiness</h4>
        <p>
            <strong>{{ $loggedInCount }}</strong> of <strong>{{ $rosterCountNow }}</strong> members have logged in to the app.
            @if($notLoggedIn->count())
                <span class="text-danger">{{ $notLoggedIn->count() }} have not logged in yet:</span>
                <a href="{{ route('admin.council-meetings.readiness.export', $meeting->id) }}" class="btn btn-xs btn-default">Export the list</a>
            @else
                <span class="text-success">✔ Everyone is ready.</span>
            @endif
        </p>
        @if($notLoggedIn->count())
        <table class="table table-condensed table-bordered">
            <thead><tr><th>Member ID</th><th>Name</th><th>Phone</th><th>Role</th></tr></thead>
            <tbody>
            @foreach($notLoggedIn as $m)
                @php($d = $details[$m->member_id] ?? null)
                <tr><td>{{ $m->member_id }}</td><td dir="auto">{{ $d->UserFullName ?? '' }}</td><td>{{ $d->MobileNumber ?? '' }}</td><td dir="auto">{{ $m->role }}</td></tr>
            @endforeach
            </tbody>
        </table>
        @endif

        @if($canManage)
        <form method="POST" action="{{ route('admin.council-meetings.destroy', $meeting->id) }}" onsubmit="return confirm('Delete this meeting and all its attendance records?')" style="margin-top:30px;">
            @csrf @method('DELETE')
            <button class="btn btn-xs btn-danger">Delete meeting</button>
        </form>
        @endif
    </div>
</div>
@endsection
