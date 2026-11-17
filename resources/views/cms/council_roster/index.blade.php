@extends($layout)

@section('footer_resources')
<script>
    $(function () {
        var csrf = '{{ csrf_token() }}';

        function flash(ok, text) {
            $('#inlineMessage').removeClass('alert-success alert-danger').addClass(ok ? 'alert-success' : 'alert-danger').text(text).show();
        }

        // ---- search + add one ----
        var searchTimeout;
        $('#memberSearchInput').on('input', function () {
            clearTimeout(searchTimeout);
            var q = $(this).val();
            $('#selectedMemberId').val('');
            if (q.length < 2) { $('#memberSearchResults').hide().empty(); return; }
            searchTimeout = setTimeout(function () {
                $.get("{{ route('admin.council-roster.search') }}", { q: q }, function (data) {
                    var html = data.length ? '' : '<div class="list-group-item">No results</div>';
                    data.forEach(function (m) {
                        html += '<a href="#" class="list-group-item member-result" data-id="' + m.member_id + '">' +
                            (m.name || '') + ' — ' + (m.phone || '') + ' — #' + m.member_id + '</a>';
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

        $('#addMemberForm').on('submit', function (e) {
            e.preventDefault();
            var id = $('#selectedMemberId').val();
            if (!id) { alert('Please pick a member from the list first'); return; }
            $.post("{{ route('admin.council-roster.store') }}", {
                _token: csrf, member_id: id,
                weight: $('#newWeight').val(), role: $('#newRole').val(),
                sector: $('#newSector').val(), district: $('#newDistrict').val()
            }).done(function (r) { location.reload(); })
              .fail(function (xhr) { flash(false, (xhr.responseJSON && xhr.responseJSON.message) || 'Something went wrong'); });
        });

        // ---- save a row / remove a row ----
        $(document).on('click', '.save-row', function () {
            var tr = $(this).closest('tr');
            $.ajax({
                url: tr.data('url'), type: 'PUT',
                data: {
                    _token: csrf, weight: tr.find('.f-weight').val(), role: tr.find('.f-role').val(),
                    sector: tr.find('.f-sector').val(), district: tr.find('.f-district').val(),
                    is_active: tr.find('.f-active').is(':checked') ? 1 : 0
                }
            }).done(function (r) { flash(true, r.message); setTimeout(function () { location.reload(); }, 600); })
              .fail(function (xhr) { flash(false, (xhr.responseJSON && xhr.responseJSON.message) || 'Something went wrong'); });
        });
        $(document).on('click', '.remove-row', function () {
            var tr = $(this).closest('tr');
            if (!confirm('Remove ' + tr.data('name') + ' from the council roster?')) return;
            $.ajax({ url: tr.data('url'), type: 'DELETE', data: { _token: csrf } })
              .done(function () { location.reload(); })
              .fail(function (xhr) { flash(false, (xhr.responseJSON && xhr.responseJSON.message) || 'Something went wrong'); });
        });
    });
</script>
@endsection

@section('content')
<div class="box box-default">
    <div class="box-header with-border">
        <h3 class="box-title">National Council members</h3>
        <a href="{{ route('admin.council-roster.export') }}" class="btn btn-success pull-right" style="margin-left:4px;">Export to Excel</a>
        <a href="{{ route('admin.council-meetings.index') }}" class="btn btn-default pull-right" style="margin-left:4px;">Meetings</a>
    </div>
    <div class="box-body">
        <div id="inlineMessage" class="alert" style="display:none;"></div>

        <div class="row" style="margin-bottom:15px;">
            <div class="col-md-3"><strong>Members:</strong> {{ $memberCount }}</div>
            <div class="col-md-3"><strong>Total weight:</strong> {{ $totalWeight }}</div>
            <div class="col-md-3"><strong>Half + 1:</strong> {{ $majority }}</div>
            <div class="col-md-3"><strong>Two-thirds:</strong> {{ $twoThirds }}</div>
        </div>
        <p class="text-muted">
            These numbers are read straight from the list below (the active members and their weights). A new meeting freezes
            them when it is created, so make sure the list is complete before creating a meeting. Adding someone later does not
            change a meeting that already exists; use "Refresh quorum" on the meeting page to update it.
        </p>

        <h4>Add a member</h4>
        <form id="addMemberForm" style="margin-bottom:20px;">
            <div style="display:flex; gap:8px; align-items:flex-start; flex-wrap:wrap;">
                <div id="memberSearchWrapper" style="position:relative; width:380px;">
                    <input type="text" id="memberSearchInput" class="form-control" autocomplete="off"
                           placeholder="Search by name, phone or member ID">
                    <div id="memberSearchResults" class="list-group"
                         style="display:none; position:absolute; z-index:1000; width:100%; max-height:260px; overflow-y:auto; margin-top:2px;"></div>
                </div>
                <input type="hidden" id="selectedMemberId">
                <input type="number" id="newWeight" class="form-control" style="width:90px;" value="1" min="0" placeholder="Weight">
                <input type="text" id="newRole" class="form-control" style="width:160px;" placeholder="Role">
                <input type="text" id="newSector" class="form-control" style="width:140px;" placeholder="Sector">
                <input type="text" id="newDistrict" class="form-control" style="width:140px;" placeholder="District">
                <button type="submit" class="btn btn-primary">Add</button>
            </div>
        </form>

        <h4>Import from Excel (adds and updates only, never removes anyone)</h4>
        <form method="POST" action="{{ route('admin.council-roster.import') }}" enctype="multipart/form-data" style="margin-bottom:25px;">
            @csrf
            <input type="file" name="excel" accept=".xlsx,.xls,.csv" style="display:inline-block;">
            <button type="submit" class="btn btn-default">Import</button>
            <a href="{{ route('admin.council-roster.template') }}">Download the Excel template</a>
            <small class="text-muted">Columns: member ID | weight | role | sector | district</small>
        </form>

        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Member ID</th>
                    <th>Name</th>
                    <th>Phone</th>
                    <th>Role</th>
                    <th>Sector</th>
                    <th>District</th>
                    <th>Weight</th>
                    <th>Active</th>
                    <th>App</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @foreach($members as $i => $m)
                @php($d = $details[$m->member_id] ?? null)
                <tr data-url="{{ route('admin.council-roster.update', $m->id) }}" data-name="{{ $d->UserFullName ?? $m->member_id }}"
                    @if(!$m->is_active) style="opacity:.5" @endif>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $m->member_id }}</td>
                    <td dir="auto">{{ $d->UserFullName ?? '— not found in the member list —' }}</td>
                    <td>{{ $d->MobileNumber ?? '' }}</td>
                    <td><input type="text" dir="auto" class="form-control f-role" value="{{ $m->role }}"></td>
                    <td><input type="text" dir="auto" class="form-control f-sector" value="{{ $m->sector }}"></td>
                    <td><input type="text" dir="auto" class="form-control f-district" value="{{ $m->district }}"></td>
                    <td><input type="number" class="form-control f-weight" style="width:80px;" min="0" value="{{ $m->weight }}"></td>
                    <td><input type="checkbox" class="f-active" @if($m->is_active) checked @endif></td>
                    <td>@if(isset($loggedIn[$m->member_id])) <span class="text-success">✔ logged in</span> @else <span class="text-danger">✖ not logged in yet</span> @endif</td>
                    <td style="white-space:nowrap;">
                        <button type="button" class="btn btn-xs btn-primary save-row">Save</button>
                        <button type="button" class="btn btn-xs btn-danger remove-row">Remove</button>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
