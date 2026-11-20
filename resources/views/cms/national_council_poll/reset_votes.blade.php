@extends($layout)

@section('content')
<section class="content-header">
    <h1>Reset votes <small>{{ $pollTitle }}</small></h1>
</section>

<section class="content">
    @if (session('message'))
        <div class="alert alert-success">{{ session('message') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="box box-danger">
        <div class="box-header with-border"><h3 class="box-title">Clear every vote of this poll</h3></div>
        <div class="box-body">
            <p>This poll has <strong>{{ $voteCount }}</strong> vote(s). Resetting clears all of them so every member can vote again. The candidates, the voters and the closing time stay as they are.</p>
            <p class="text-muted">Nothing is lost for good: every vote is copied into a backup table before it is cleared. A code is sent by SMS to the system's fixed phone and must be typed here.</p>

            @if (!$phoneSet)
                <div class="alert alert-warning">The code phone number is not set on this server (INTERNAL_ELECTION_RESULTS_OTP_PHONE), so a reset is not possible here.</div>
            @else
                <form method="post" action="{{ route('admin.national-council-poll.reset-votes.send', $poll->id) }}" style="margin-bottom:20px">
                    @csrf
                    <button type="submit" class="btn btn-default">{{ $codeSentAt ? 'Send a new code' : 'Send code by SMS' }}</button>
                    @if ($codeSentAt)
                        <span class="text-muted" style="margin-left:10px">Last sent {{ $codeSentAt->diffForHumans() }} (valid 5 minutes)</span>
                    @endif
                </form>

                <form method="post" action="{{ route('admin.national-council-poll.reset-votes.confirm', $poll->id) }}" class="form-inline"
                      onsubmit="return confirm('Clear ALL {{ $voteCount }} vote(s) of this poll?')">
                    @csrf
                    <input type="text" name="code" class="form-control" placeholder="Code from the SMS" autocomplete="off" inputmode="numeric" style="width:220px" required>
                    <button type="submit" class="btn btn-danger">Reset votes</button>
                    <a href="{{ route('admin.national-council-poll.index') }}" class="btn btn-default">Cancel</a>
                </form>
            @endif
        </div>
    </div>
</section>
@endsection
