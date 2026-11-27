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
        // ---- filters: role / sector / district / free text / logged in the app, all combined ----
        function applyFilters() {
            var role = $('#filterRole').val(), sector = $('#filterSector').val(), district = $('#filterDistrict').val();
            var text = $.trim($('#filterText').val()).toLowerCase();
            var app = $('#filterApp').val();
            var shown = 0, weight = 0, total = 0, ids = [];

            $('#rosterTable tbody tr').each(function () {
                var tr = $(this);
                total++;
                var ok = (!role || tr.attr('data-role') === role)
                      && (!sector || tr.attr('data-sector') === sector)
                      && (!district || tr.attr('data-district') === district)
                      && (!app || tr.attr('data-app') === app)
                      && (!text || String(tr.attr('data-search')).indexOf(text) !== -1);
                tr.toggle(ok);
                if (ok) { shown++; ids.push(tr.attr('data-id')); weight += parseInt(tr.find('.f-weight').val(), 10) || 0; }
            });

            var filtering = role || sector || district || text || app;
            $('#filterSummary').text(filtering
                ? 'Showing ' + shown + ' of ' + total + ' members (total weight ' + weight + ')'
                : '');

            // the Excel button gives what is on the screen: the filtered rows, or everybody
            var link = $('#exportLink');
            link.attr('href', link.attr('data-all') + (filtering ? '?ids=' + (ids.join(',') || '0') : ''));
        }
        $('#filterRole, #filterSector, #filterDistrict, #filterApp').on('change', applyFilters);
        $('#filterText').on('input', applyFilters);
        $('#filterClear').on('click', function () {
            $('#filterRole, #filterSector, #filterDistrict, #filterApp').val('');
            $('#filterText').val('');
            applyFilters();
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
@include('cms.council_meetings._member_photo_style')
<div class="box box-default">
    <div class="box-header with-border">
        <h3 class="box-title">National Council members</h3>
        <a id="exportLink" href="{{ route('admin.council-roster.export') }}" data-all="{{ route('admin.council-roster.export') }}" class="btn btn-success pull-right" style="margin-left:4px;">Export to Excel</a>
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

        @php($roleOptions = $members->pluck('role')->map(fn($v) => trim((string) $v))->filter()->unique()->sort()->values())
        @php($districtOptions = $members->pluck('district')->map(fn($v) => trim((string) $v))->filter()->unique()->sort()->values())
        @php($sectorOptions = $members->pluck('sector')->map(fn($v) => trim((string) $v))->filter()->unique()->sort()->values())

        <div id="rosterFilters" style="display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end; margin:5px 0 10px;">
            <div>
                <label>Role</label>
                <select id="filterRole" class="form-control" style="width:220px;" dir="auto">
                    <option value="">All roles</option>
                    @foreach($roleOptions as $opt)<option value="{{ $opt }}">{{ $opt }}</option>@endforeach
                </select>
            </div>
            <div>
                <label>Sector</label>
                <select id="filterSector" class="form-control" style="width:180px;" dir="auto">
                    <option value="">All sectors</option>
                    @foreach($sectorOptions as $opt)<option value="{{ $opt }}">{{ $opt }}</option>@endforeach
                </select>
            </div>
            <div>
                <label>District</label>
                <select id="filterDistrict" class="form-control" style="width:220px;" dir="auto">
                    <option value="">All districts</option>
                    @foreach($districtOptions as $opt)<option value="{{ $opt }}">{{ $opt }}</option>@endforeach
                </select>
            </div>
            <div>
                <label>Search the list</label>
                <input type="text" id="filterText" class="form-control" style="width:220px;" dir="auto" placeholder="Name, member ID or phone">
            </div>
            <div>
                <label>App</label>
                <select id="filterApp" class="form-control" style="width:180px;">
                    <option value="">All members</option>
                    <option value="in">Logged in</option>
                    <option value="no">Not logged in yet</option>
                </select>
            </div>
            <div>
                <button type="button" id="filterClear" class="btn btn-default">Clear filters</button>
            </div>
            <div id="filterSummary" class="text-muted" style="padding-bottom:7px;"></div>
        </div>

        <p class="text-muted" style="margin:0 0 8px;">
            <strong>Logged in</strong> means the member was active in the app
            @if($trackingSince) since activity tracking began ({{ \Carbon\Carbon::parse($trackingSince)->format('j M Y') }}) @else (no activity recorded yet) @endif.
            An old account that has not been used since then shows as "not logged in yet".
        </p>
        <table class="table table-bordered table-striped" id="rosterTable">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Photo</th>
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
                <tr data-id="{{ $m->id }}" data-url="{{ route('admin.council-roster.update', $m->id) }}" data-name="{{ $d->UserFullName ?? $m->member_id }}"
                    data-role="{{ trim((string) $m->role) }}" data-sector="{{ trim((string) $m->sector) }}" data-district="{{ trim((string) $m->district) }}"
                    data-app="{{ isset($loggedIn[(string) $m->member_id]) ? 'in' : 'no' }}"
                    data-search="{{ mb_strtolower(($d->UserFullName ?? '') . ' ' . $m->member_id . ' ' . ($d->MobileNumber ?? '')) }}"
                    @if(!$m->is_active) style="opacity:.5" @endif>
                    <td>{{ $i + 1 }}</td>
                    <td><img class="mphoto" loading="lazy" alt="" src="{{ route('admin.council-roster.photo', $m->member_id) }}" onerror="this.onerror=null;this.src=window.MPH"></td>
                    <td>{{ $m->member_id }}</td>
                    <td dir="auto">{{ $d->UserFullName ?? '— not found in the member list —' }}</td>
                    <td>{{ $d->MobileNumber ?? '' }}</td>
                    <td><input type="text" dir="auto" class="form-control f-role" value="{{ $m->role }}"></td>
                    <td><input type="text" dir="auto" class="form-control f-sector" value="{{ $m->sector }}"></td>
                    <td><input type="text" dir="auto" class="form-control f-district" value="{{ $m->district }}"></td>
                    <td><input type="number" class="form-control f-weight" style="width:80px;" min="0" value="{{ $m->weight }}"></td>
                    <td><input type="checkbox" class="f-active" @if($m->is_active) checked @endif></td>
                    <td>
                        @if(isset($loggedIn[(string) $m->member_id]))
                            <span class="text-success">✔ logged in</span>
                            <div class="text-muted" style="font-size:11px;">last seen {{ \Carbon\Carbon::parse($loggedIn[(string) $m->member_id])->format('j M Y') }}</div>
                        @else
                            <span class="text-danger">✖ not logged in yet</span>
                            @if(isset($hasAccount[$m->member_id]))<div class="text-muted" style="font-size:11px;">old account only</div>@endif
                        @endif
                        @php($app = $appInfo[(string) $m->member_id] ?? null)
                        @if($app && $app['version'] !== '')<div style="font-size:11px;">App {{ $app['version'] }}</div>@endif
                        @if($app && $app['model'] !== '')<div class="text-muted" style="font-size:11px;">{{ $app['model'] }}</div>@endif
                    </td>
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
