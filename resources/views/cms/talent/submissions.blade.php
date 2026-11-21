@extends($layout)

@section('content')
<section class="content-header">
    <h1>FPM Talent <small>submissions</small></h1>
</section>

<section class="content">
    @if (session('message'))
        <div class="alert alert-success">{{ session('message') }}</div>
    @endif

    <div style="margin-bottom:12px">
        @foreach ($statuses as $key => $label)
            <span class="label label-default" style="margin-right:6px;font-size:12px">{{ $label }}: {{ $counts[$key] ?? 0 }}</span>
        @endforeach
    </div>

    <div class="box box-default">
        <div class="box-body">
            <form method="GET" action="{{ route('admin.talent-submissions.index') }}" class="form-inline">
                <select name="track" class="form-control">
                    <option value="">All tracks</option>
                    @foreach ($tracks as $code => $title)
                        <option value="{{ $code }}" @if (request('track') === $code) selected @endif>{{ $title }}</option>
                    @endforeach
                </select>
                <select name="status" class="form-control">
                    <option value="">Any status</option>
                    @foreach ($statuses as $key => $label)
                        <option value="{{ $key }}" @if (request('status') === $key) selected @endif>{{ $label }}</option>
                    @endforeach
                </select>
                <select name="who" class="form-control">
                    <option value="">Members + guests</option>
                    <option value="member" @if (request('who') === 'member') selected @endif>Members</option>
                    <option value="guest" @if (request('who') === 'guest') selected @endif>Guests</option>
                </select>
                <select name="cycle" class="form-control">
                    <option value="">Any cycle</option>
                    @foreach ($cycles as $c)
                        <option value="{{ $c }}" @if ((string) request('cycle') === (string) $c) selected @endif>Cycle {{ $c }}</option>
                    @endforeach
                </select>
                <input type="text" name="q" value="{{ request('q') }}" class="form-control" placeholder="Name, phone, member ID, profession" style="width:260px">
                <button type="submit" class="btn btn-default"><i class="fa fa-search"></i> Filter</button>
                <a href="{{ route('admin.talent-submissions.index', ['track' => 'peer', 'group' => 'nominee'] + request()->except(['track', 'group', 'page'])) }}" class="btn btn-default" title="Peer track: the same person named by several members">People named by several members</a>
                <a href="{{ route('admin.talent-submissions.export', request()->except(['group', 'page'])) }}" class="btn btn-success"><i class="fa fa-download"></i> Export (Excel)</a>
            </form>
        </div>
    </div>

    @if ($grouped)
        <div class="box box-default">
            <div class="box-header with-border"><h3 class="box-title">People named by members (peer track), most-named first</h3></div>
            <div class="box-body">
                <table class="table table-bordered table-striped">
                    <thead><tr><th>Nominee</th><th>Named by (different people)</th><th>Times</th><th>Last</th><th></th></tr></thead>
                    <tbody>
                    @forelse ($grouped as $g)
                        <tr>
                            <td><bdi>{{ $g->nominee_name }}</bdi></td><td>{{ $g->people }}</td><td>{{ $g->times }}</td><td>{{ $g->last_at }}</td>
                            <td><a href="{{ route('admin.talent-submissions.index', ['track' => 'peer', 'q' => $g->nominee_name]) }}">see</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-muted">Nothing yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @else
        <div class="box box-default">
            <div class="box-body table-responsive">
                <table class="table table-bordered table-striped">
                    <thead><tr><th>#</th><th>Date</th><th>Track</th><th>Cycle</th><th>From</th><th>Nominee / roles</th><th>Files</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    @forelse ($rows as $s)
                        <tr>
                            <td>{{ $s->id }}</td>
                            <td>{{ $s->created_at }}</td>
                            <td>{{ $tracks[$s->track] ?? $s->track }}</td>
                            <td>{{ $s->cycle }}</td>
                            <td>
                                <bdi>{{ $s->submitter_name }}</bdi>
                                <br><small class="text-muted">@if ($s->is_guest)<span class="label label-warning">Guest</span> {{ $s->submitter_phone }}@else Member {{ $s->member_id }}@endif</small>
                            </td>
                            <td>
                                @if ($s->nominee_name)<b><bdi>{{ $s->nominee_name }}</bdi></b><br>@endif
                                <small>{{ collect($s->roles())->pluck('name')->implode(' · ') }}</small>
                            </td>
                            <td>{{ $s->attachments_count }}</td>
                            <td><span class="label label-{{ ['new' => 'info', 'shortlisted' => 'primary', 'accepted' => 'success', 'rejected' => 'danger'][$s->status] ?? 'default' }}">{{ $statuses[$s->status] ?? $s->status }}</span></td>
                            <td><a class="btn btn-xs btn-primary" href="{{ route('admin.talent-submissions.show', $s->id) }}">Open</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="text-muted">No submissions match.</td></tr>
                    @endforelse
                    </tbody>
                </table>
                {{ $rows->links() }}
            </div>
        </div>
    @endif
</section>
@endsection
