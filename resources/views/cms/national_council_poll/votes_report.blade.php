@extends($layout)

@section('content')
<div class="box box-danger">
    <div class="box-header with-border">
        <h3 class="box-title" dir="auto">{{ $poll->title }} <small>&mdash; who voted for whom (restricted)</small></h3>
        <a href="{{ route('admin.national-council-poll.index') }}" class="btn btn-default pull-right" style="margin-left:4px;">Back</a>
    </div>
    <div class="box-body">
        <table class="table table-bordered table-striped">
            <thead>
                <tr><th>Voter</th><th>Member ID</th><th>Weight</th><th>Question</th><th>Voted for</th><th>Voted at</th><th>Phone of the app account</th></tr>
            </thead>
            <tbody>
            @forelse($votes as $v)
                <tr>
                    <td dir="auto">{{ $v['voter'] }}</td>
                    <td>{{ $v['member_id'] }}</td>
                    <td>{{ $v['weight'] }}</td>
                    <td dir="auto">{{ $v['question'] }}</td>
                    <td dir="auto"><strong>{{ $v['candidate'] }}</strong></td>
                    <td>{{ \Carbon\Carbon::parse($v['voted_at'])->format('j M Y, H:i') }}</td>
                    <td>{{ $v['phone'] }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center">No votes match.</td></tr>
            @endforelse
            </tbody>
        </table>
        {{ $votes->links() }}
    </div>
</div>
@endsection
