@extends($layout)

@section('content')
<div class="box box-default">
    <div class="box-header with-border">
        <h3 class="box-title">New meeting</h3>
    </div>
    <div class="box-body">
        <form method="POST" action="{{ route('admin.council-meetings.store') }}" style="max-width:560px;">
            @csrf
            <div class="form-group">
                <label>Meeting title</label>
                <input type="text" name="title" class="form-control" required value="{{ old('title') }}" placeholder="e.g. National Council meeting, 29 November 2026">
            </div>
            <div class="form-group">
                <label>Date</label>
                <input type="date" name="meeting_date" class="form-control" value="{{ old('meeting_date') }}">
            </div>
            <div class="alert alert-info">
                The quorum is frozen when the meeting is created, from the council roster as it is right now:
                <strong>{{ $memberCount }}</strong> members, total weight <strong>{{ $totalWeight }}</strong>,
                half + 1 = <strong>{{ $majority }}</strong>, two-thirds = <strong>{{ $twoThirds }}</strong>.
                <br>Make sure the roster is complete before creating the meeting. You can update the quorum later with "Refresh quorum" on the meeting page.
            </div>
            <button type="submit" class="btn btn-primary">Create meeting</button>
            <a href="{{ route('admin.council-meetings.index') }}" class="btn btn-default">Cancel</a>
        </form>
    </div>
</div>
@endsection
