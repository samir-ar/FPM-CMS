@extends($layout)

@section('content')
<style>
    .dev { display:inline-block; font-family: monospace; font-size:13px; color:#223; background:#eef1f4; border:1px solid #cfd8de; border-radius:4px; padding:3px 7px; word-break:break-all; max-width:300px; line-height:1.35; }
</style>
<div class="box box-danger">
    <div class="box-header with-border">
        <h3 class="box-title" dir="auto">{{ $poll->title }} <small>&mdash; who voted for whom (restricted)</small></h3>
        <a href="{{ route('admin.national-council-poll.index') }}" class="btn btn-default pull-right" style="margin-left:4px;">Back</a>
        <a href="{{ route('admin.national-council-poll.votes-report.export', array_merge(['id' => $poll->id], request()->query())) }}" class="btn btn-success pull-right" style="margin-left:4px;">Export to Excel</a>
    </div>
    <div class="box-body">
        <div class="callout callout-warning">
            This report shows <strong>how each person voted</strong>. Treat it as confidential. It is not linked from any menu.
        </div>

        <form method="GET" class="form-inline" style="margin-bottom:12px;">
            <select name="question_id" class="form-control" style="max-width:300px;">
                <option value="">All questions</option>
                @foreach($questions as $q)<option value="{{ $q['id'] }}" @if($selectedQuestion === $q['id']) selected @endif>{{ $q['text'] }}</option>@endforeach
            </select>
            <select name="answer_id" class="form-control" style="max-width:300px;">
                <option value="">Everyone voted for</option>
                @foreach($candidates as $c)<option value="{{ $c['id'] }}" @if($selectedAnswer === $c['id']) selected @endif>{{ $c['text'] }}</option>@endforeach
            </select>
            <label style="margin:0 8px;font-weight:normal;"><input type="checkbox" name="shared" value="1" @if($onlyShared) checked @endif> only shared devices</label>
            <button class="btn btn-primary">Filter</button>
            <a href="{{ route('admin.national-council-poll.votes-report', $poll->id) }}" class="btn btn-default">Clear</a>
        </form>

        <p class="text-muted">{{ $votes->total() }} of {{ $total }} vote{{ $total === 1 ? '' : 's' }}.</p>
        <table class="table table-bordered table-striped">
            <thead>
                <tr><th>Voter</th><th>Member ID</th><th>Weight</th><th>Question</th><th>Voted for</th><th>Voted at</th><th>Phone used</th><th>App version</th><th>Phone of the app account</th></tr>
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
                    <td>
                        @include('cms.national_council_poll._phone_cell', ['model' => $v['device_model'], 'hardware' => $v['hardware'], 'device' => $v['device']])
                        @if($v['device_members'] > 1)<br><span class="label label-warning" style="display:inline-block;margin-top:4px;" title="The same phone voted for {{ $v['device_members'] }} members">shared by {{ $v['device_members'] }}</span>@endif
                    </td>
                    <td>{{ $v['app_version'] ?: '—' }}</td>
                    <td>{{ $v['phone'] }}</td>
                </tr>
            @empty
                <tr><td colspan="9" class="text-center">No votes match.</td></tr>
            @endforelse
            </tbody>
        </table>
        {{ $votes->links() }}
    </div>
</div>
@endsection
