@extends($layout)

@section('footer_resources')
<script>
    $(function () {
        $(document).on('click', '.add-to-roster', function () {
            var tr = $(this).closest('tr');
            $.post("{{ route('admin.council-roster.store') }}", {
                _token: '{{ csrf_token() }}',
                member_id: tr.data('id'),
                weight: tr.find('.roster-weight').val(),
                role: tr.find('.roster-role').val(),
                sector: tr.data('sector')
            }).done(function (r) { alert(r.message); location.reload(); })
              .fail(function (xhr) { alert((xhr.responseJSON && xhr.responseJSON.message) || 'Something went wrong'); });
        });
    });
</script>
@endsection

@section('content')
<div class="box box-default">
    <div class="box-header with-border">
        <h3 class="box-title">Special members (no official member number)</h3>
    </div>
    <div class="box-body">
        <p class="text-muted">
            For a person who has to log in and be counted (for example a National Council member) but has no official TWH member
            number. They get a number from a reserved range that can never clash with a real one (13 digits starting with 99) and a
            record in the member list, so login, attendance and voting work like for any member. A copy of their details is kept
            here and can be restored if the TWH sync ever removes the record.
        </p>

        <h4>Add a special member</h4>
        <form method="POST" action="{{ route('admin.special-members.store') }}" enctype="multipart/form-data" style="max-width:640px; margin-bottom:30px;">
            @csrf
            <div class="form-group">
                <label>Full name *</label>
                <input type="text" name="full_name" dir="auto" class="form-control" required value="{{ old('full_name') }}">
            </div>
            <div class="form-group">
                <label>Phone number with country code * (this is the login number)</label>
                <input type="text" name="phone" class="form-control" required dir="ltr" value="{{ old('phone') }}" placeholder="+1 5122990023">
            </div>
            <div class="row">
                <div class="col-md-6 form-group">
                    <label>Sector / role</label>
                    <input type="text" name="sector" dir="auto" class="form-control" value="{{ old('sector') }}">
                </div>
                <div class="col-md-6 form-group">
                    <label>Region / district</label>
                    <input type="text" name="district" dir="auto" class="form-control" value="{{ old('district') }}">
                </div>
            </div>
            <div class="form-group">
                <label>Email (optional)</label>
                <input type="email" name="email" class="form-control" dir="ltr" value="{{ old('email') }}">
            </div>
            <div class="form-group">
                <label>Photo (resized automatically)</label>
                <input type="file" name="photo" accept="image/*">
            </div>
            <div class="form-group">
                <label>Note</label>
                <textarea name="note" dir="auto" class="form-control" rows="2">{{ old('note') }}</textarea>
            </div>
            <button type="submit" class="btn btn-primary">Add</button>
        </form>

        <h4>Special members ({{ $members->count() }})</h4>
        <table class="table table-bordered table-striped">
            <thead>
                <tr><th>Member ID</th><th>Name</th><th>Phone</th><th>Sector</th><th>In the member list</th><th>Logged in</th><th>Council roster</th><th></th></tr>
            </thead>
            <tbody>
            @forelse($members as $m)
                @php($row = $inFpm[(string) $m->member_id] ?? null)
                <tr data-id="{{ $m->member_id }}" data-sector="{{ $m->sector }}">
                    <td>{{ $m->member_id }}</td>
                    <td dir="auto">{{ $m->full_name }}</td>
                    <td dir="ltr">+{{ $m->phone }}</td>
                    <td dir="auto">{{ $m->sector }}</td>
                    <td>
                        @if($row)
                            {!! $row->member_status ? '<span class="text-success">✔ active</span>' : '<span class="text-warning">disabled</span>' !!}
                        @else
                            <span class="text-danger">✖ missing</span>
                        @endif
                    </td>
                    <td>@if(isset($loggedIn[(string) $m->member_id])) <span class="text-success">✔</span> @else <span class="text-muted">no</span> @endif</td>
                    <td>
                        @if(isset($inRoster[(string) $m->member_id]))
                            <span class="text-success">✔ added</span>
                        @else
                            <input type="number" class="roster-weight" style="width:60px;" min="0" value="1" title="Weight">
                            <input type="text" dir="auto" class="roster-role" style="width:110px;" placeholder="Role" value="{{ $m->sector }}">
                            <button type="button" class="btn btn-xs btn-primary add-to-roster">Add to council</button>
                        @endif
                    </td>
                    <td style="white-space:nowrap;">
                        @if(!$row)
                            <a class="btn btn-xs btn-warning" href="{{ route('admin.special-members.restore', $m->id) }}" onclick="return confirm('Put the record back into the member list?')">Restore</a>
                        @else
                            <a class="btn btn-xs btn-default" href="{{ route('admin.special-members.toggle-status', $m->id) }}">{{ $row->member_status ? 'Disable' : 'Enable' }}</a>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center">No special members yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
