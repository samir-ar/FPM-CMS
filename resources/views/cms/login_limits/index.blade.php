@extends($layout)

@section('content')
<section class="content-header">
    <h1>Login limits <small>reset the app login counters</small></h1>
</section>

<section class="content">
    @if (session('message'))
        <div class="alert alert-success">{{ session('message') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="box box-default">
        <div class="box-header with-border"><h3 class="box-title">One member</h3></div>
        <div class="box-body">
            <form method="get" action="{{ route('admin.users.login-limits') }}" class="form-inline" style="margin-bottom:15px">
                <input type="text" name="member_id" value="{{ $member }}" class="form-control" placeholder="Member ID" style="width:220px" inputmode="numeric">
                <button type="submit" class="btn btn-default">Check</button>
            </form>

            @if ($status)
                <p>
                    <strong>{{ $member }}</strong>@if ($name) &mdash; {{ $name }}@endif
                </p>
                <table class="table table-bordered" style="max-width:520px">
                    <tr>
                        <td>Codes requested (today, 3 per day)</td>
                        <td>{{ $status['sent'] }} of {{ $status['sent_max'] }}</td>
                        <td>@if ($status['sent_blocked'])<span class="label label-danger">blocked</span>@else<span class="label label-success">ok</span>@endif</td>
                    </tr>
                    <tr>
                        <td>Wrong codes typed (last 15 min)</td>
                        <td>{{ $status['wrong'] }} of {{ $status['wrong_max'] }}</td>
                        <td>@if ($status['wrong_blocked'])<span class="label label-danger">locked</span>@else<span class="label label-success">ok</span>@endif</td>
                    </tr>
                </table>

                <form method="post" action="{{ route('admin.users.login-limits.reset-member') }}">
                    @csrf
                    <input type="hidden" name="member_id" value="{{ $member }}">
                    <button type="submit" class="btn btn-primary">Reset this member</button>
                </form>
            @else
                <p class="text-muted">Type a member id and press Check to see how many codes they asked for and how many wrong codes they typed, then reset them.</p>
            @endif
        </div>
    </div>

    <div class="box box-danger">
        <div class="box-header with-border"><h3 class="box-title">Everybody</h3></div>
        <div class="box-body">
            <p>Starts every counter from zero for <strong>all members</strong>, including the per-IP wrong-code counter (a member can be blocked because many wrong codes came from the same IP address; only this button clears that one).</p>
            <form method="post" action="{{ route('admin.users.login-limits.reset-all') }}" onsubmit="return confirm('Reset the login limits for EVERY member?')">
                @csrf
                <button type="submit" class="btn btn-danger">Reset everybody</button>
            </form>
        </div>
    </div>

    <div class="box box-default">
        <div class="box-header with-border"><h3 class="box-title">Who tried to log in (last 24 hours)</h3></div>
        <div class="box-body table-responsive">
            @if (count($recent))
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Member ID</th><th>Name</th><th>Codes requested<br><small class="text-muted">history, 24 h</small></th><th>Wrong codes<br><small class="text-muted">history, 24 h</small></th><th>Last try</th><th>Current<br><small class="text-muted">used now (reset clears)</small></th><th>Now</th><th></th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach ($recent as $r)
                        <tr>
                            <td><a href="{{ route('admin.users.login-limits', ['member_id' => $r['member_id']]) }}">{{ $r['member_id'] }}</a></td>
                            <td>{{ $r['name'] }}</td>
                            <td>{{ $r['codes'] }}</td>
                            <td>{{ $r['wrong'] }}</td>
                            <td>{{ $r['last_try'] }}</td>
                            <td>
                                Codes: <strong>{{ $r['cur_sent'] }}</strong> of {{ $r['cur_sent_max'] }}<br>
                                Wrong: <strong>{{ $r['cur_wrong'] }}</strong> of {{ $r['cur_wrong_max'] }}
                            </td>
                            <td>
                                @if ($r['blocked'])
                                    <span class="label label-danger">Blocked</span> <small>{{ $r['why'] }}</small>
                                @else
                                    <span class="label label-success">OK</span>
                                @endif
                            </td>
                            <td>
                                <form method="post" action="{{ route('admin.users.login-limits.reset-member') }}" style="margin:0">
                                    @csrf
                                    <input type="hidden" name="member_id" value="{{ $r['member_id'] }}">
                                    <button type="submit" class="btn btn-xs btn-primary">Reset</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                <p class="text-muted" style="margin-bottom:0">Wrong codes are listed only when the member's login request was recognised. "Now" shows whether they are blocked at this moment (the block ends by itself 15 minutes after their last try, or when you reset).</p>
            @else
                <p class="text-muted" style="margin-bottom:0">Nobody has asked for a code in the last 24 hours (the list starts filling from the moment this update went live).</p>
            @endif
        </div>
    </div>

    <p class="text-muted">A reset only lets people ask for codes and type codes again. It does not touch accounts, votes or logins. Every reset is written to the server log.</p>
</section>
@endsection
