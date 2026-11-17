@extends($layout)

@section('content')
<div class="box box-default">
    <div class="box-header with-border">
        <h3 class="box-title">National Council meetings</h3>
        <a href="{{ route('admin.council-meetings.create') }}" class="btn btn-primary pull-right" style="margin-left:4px;">New meeting</a>
        <a href="{{ route('admin.council-roster.index') }}" class="btn btn-default pull-right" style="margin-left:4px;">Council roster</a>
    </div>
    <div class="box-body">
        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Meeting</th>
                    <th>Date</th>
                    <th>Present</th>
                    <th>Present weight / total</th>
                    <th>Half + 1</th>
                    <th>Two-thirds</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse($meetings as $row)
                @php($m = $row['meeting'])
                @php($s = $row['stats'])
                <tr>
                    <td>{{ $m->id }}</td>
                    <td dir="auto">{{ $m->title }}</td>
                    <td>{{ optional($m->meeting_date)->format('Y-m-d') }}</td>
                    <td>{{ $s['present_count'] }} / {{ $s['member_count'] }}</td>
                    <td>{{ $s['present_weight'] }} / {{ $s['total_weight'] }}</td>
                    <td><span class="{{ $s['majority_reached'] ? 'text-success' : 'text-danger' }}">{{ $s['majority_reached'] ? '✔' : '✖' }} {{ $s['quorum_majority'] }}</span></td>
                    <td><span class="{{ $s['two_thirds_reached'] ? 'text-success' : 'text-danger' }}">{{ $s['two_thirds_reached'] ? '✔' : '✖' }} {{ $s['quorum_two_thirds'] }}</span></td>
                    <td>{!! $m->is_active ? '<span class="text-success">Open</span>' : '<span class="text-warning">Closed</span>' !!}</td>
                    <td><a class="btn btn-xs btn-primary" href="{{ route('admin.council-meetings.show', $m->id) }}">Attendance</a></td>
                </tr>
            @empty
                <tr><td colspan="9" class="text-center">No meetings yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
