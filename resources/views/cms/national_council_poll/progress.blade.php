@extends($layout)

@section('content')
@include('cms.council_meetings._member_photo_style')
<style>
    .tile { background:#fff; border:1px solid #e3e8ec; border-radius:6px; padding:14px 18px; margin-bottom:12px; }
    .tile .n { font-size:28px; font-weight:700; line-height:1.1; }
    .tile .l { color:#667; font-size:12px; text-transform:uppercase; letter-spacing:.06em; }
    .dev { display:inline-block; font-family: monospace; font-size:13px; color:#223; background:#eef1f4; border:1px solid #cfd8de; border-radius:4px; padding:3px 7px; word-break:break-all; max-width:300px; line-height:1.35; }
    tr.not-voted td { opacity:.65; }
</style>

<div class="box box-default">
    <div class="box-header with-border">
        <h3 class="box-title" dir="auto">{{ $poll->title }} <small>&mdash; who voted</small></h3>
        <a href="{{ route('admin.national-council-poll.index') }}" class="btn btn-default pull-right" style="margin-left:4px;">Back</a>
        <a href="{{ route('admin.national-council-poll.progress.export', $poll->id) }}" class="btn btn-success pull-right" style="margin-left:4px;">Export to Excel</a>
    </div>
    <div class="box-body">
        <div style="margin-bottom:8px;">
            <span id="livePill" class="label label-success">Live</span>
            <label style="margin:0 0 0 8px;font-weight:normal;"><input type="checkbox" id="liveToggle" checked> Update by itself every 5 seconds</label>
        </div>
        <p class="text-muted">This page shows <strong>who has voted and from which device</strong>. It never shows <em>what</em> anybody voted.</p>

        <div class="row" id="liveTiles">
            <div class="col-md-3"><div class="tile"><div class="l">Voters allowed</div><div class="n">{{ $summary['voters'] }}</div><div class="text-muted">weight {{ $summary['voters_weight'] }}</div></div></div>
            <div class="col-md-3"><div class="tile"><div class="l">Voted</div><div class="n text-success">{{ $summary['voted'] }}</div><div class="text-muted">weight {{ $summary['voted_weight'] }}</div></div></div>
            <div class="col-md-3"><div class="tile"><div class="l">Still to vote</div><div class="n text-danger">{{ $summary['remaining'] }}</div></div></div>
            <div class="col-md-3"><div class="tile"><div class="l">Turnout</div><div class="n">{{ $summary['turnout'] }}%</div><div class="text-muted">{{ $summary['turnout_weight'] }}% of the weight</div></div></div>
        </div>

        <div id="liveShared">
        @if($sharedDevices->count())
            <div class="callout callout-warning">
                <h4><i class="fa fa-warning"></i> A device was used to vote for more than one member</h4>
                @foreach($sharedDevices as $d)
                    <p style="margin-bottom:6px;">
                        @include('cms.national_council_poll._phone_cell', ['model' => $d['model'], 'hardware' => $d['hardware'], 'device' => $d['device_id']]) &mdash;
                        @foreach($d['members'] as $m)<span dir="auto">{{ $m['name'] }}</span> (#{{ $m['id'] }})@if(!$loop->last), @endif @endforeach
                    </p>
                @endforeach
                <a href="{{ request()->fullUrlWithQuery(['shared' => 1, 'status' => 'voted']) }}">Show only those votes</a>
            </div>
        @endif

        </div>

        <form method="GET" class="form-inline" style="margin-bottom:12px;">
            <select name="status" class="form-control">
                <option value="all" @if($status === 'all') selected @endif>Everyone</option>
                <option value="voted" @if($status === 'voted') selected @endif>Voted</option>
                <option value="not_voted" @if($status === 'not_voted') selected @endif>Not voted yet</option>
            </select>
            <input type="text" name="q" value="{{ $q }}" class="form-control" placeholder="Name, member ID or phone" style="width:260px;">
            <label style="margin:0 8px;font-weight:normal;"><input type="checkbox" name="shared" value="1" @if($onlyShared) checked @endif> only shared devices</label>
            <button class="btn btn-primary">Filter</button>
            <a href="{{ route('admin.national-council-poll.progress', $poll->id) }}" class="btn btn-default">Clear</a>
        </form>

        <p class="text-muted" id="liveCount">Showing {{ $rows->count() }} {{ $rows->count() === 1 ? 'person' : 'people' }}.</p>
        <table class="table table-bordered table-striped">
            <thead>
                <tr><th>Photo</th><th>Member ID</th><th>Name</th><th>Role</th><th>Weight</th><th>Voted</th><th>Phone used</th><th>Phone of the app account</th></tr>
            </thead>
            <tbody id="liveRows">
            @forelse($rows as $r)
                <tr class="{{ $r['voted'] ? '' : 'not-voted' }}">
                    <td><img class="mphoto" loading="lazy" alt="" src="{{ route('admin.council-roster.photo', $r['member_id']) }}" onerror="this.onerror=null;this.src=window.MPH"></td>
                    <td>{{ $r['member_id'] }}</td>
                    <td dir="auto">{{ $r['name'] }}@if(!$r['permitted']) <span class="label label-danger" title="This person voted but is not on the voter list">not on the list</span>@endif</td>
                    <td dir="auto">{{ $r['role'] }}</td>
                    <td>{{ $r['weight'] }}</td>
                    <td>
                        @if($r['voted']) <span class="text-success">&#10004; {{ \Carbon\Carbon::parse($r['voted_at'])->format('j M Y, H:i') }}</span>
                        @else <span class="text-danger">&#10006; not yet</span> @endif
                    </td>
                    <td>
                        @if($r['device'] || $r['hardware'] || $r['device_model'])
                            @include('cms.national_council_poll._phone_cell', ['model' => $r['device_model'], 'hardware' => $r['hardware'], 'device' => $r['device']])
                            @if($r['device_members'] > 1)<br><span class="label label-warning" style="display:inline-block;margin-top:4px;" title="The same phone voted for {{ $r['device_members'] }} members">shared by {{ $r['device_members'] }}</span>@endif
                        @elseif($r['voted'])<span class="text-muted">not recorded</span>@endif
                    </td>
                    <td>{{ $r['phone'] }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center">Nobody matches this filter.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

@section('footer_resources')
<script>
(function () {
    // Live page: every 5 seconds the page reads ITSELF again (same address, so the filters stay) and swaps in only the
    // parts that changed: the four totals, the shared-device warning, the "showing N people" line and the table rows.
    // The filter form is never touched, so what you typed stays. Paused while the tab is hidden or the box is unticked.
    var PARTS = ['liveTiles', 'liveShared', 'liveCount', 'liveRows'];
    var pill = document.getElementById('livePill');
    var toggle = document.getElementById('liveToggle');
    var timer = null;

    function say(text, cls) {
        pill.className = 'label ' + cls;
        pill.textContent = text;
    }
    function clock() {
        var d = new Date();
        return ('0' + d.getHours()).slice(-2) + ':' + ('0' + d.getMinutes()).slice(-2) + ':' + ('0' + d.getSeconds()).slice(-2);
    }
    function schedule() {
        clearTimeout(timer);
        timer = setTimeout(tick, 5000);
    }
    function tick() {
        if (!toggle.checked || document.hidden) { schedule(); return; }
        fetch(location.href, { credentials: 'same-origin', cache: 'no-store', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { if (!r.ok) { throw new Error('http ' + r.status); } return r.text(); })
            .then(function (html) {
                var doc = new DOMParser().parseFromString(html, 'text/html');
                if (!doc.getElementById('liveTiles')) { say('Session ended: reload the page', 'label-danger'); return; }
                PARTS.forEach(function (id) {
                    var now = document.getElementById(id), fresh = doc.getElementById(id);
                    if (now && fresh && now.innerHTML !== fresh.innerHTML) { now.innerHTML = fresh.innerHTML; }
                });
                say('Live · updated ' + clock(), 'label-success');
            })
            .catch(function () { say('Connection lost, trying again', 'label-warning'); })
            .then(schedule);
    }
    toggle.addEventListener('change', function () {
        if (toggle.checked) { say('Live', 'label-success'); tick(); } else { say('Paused', 'label-default'); }
    });
    document.addEventListener('visibilitychange', function () { if (!document.hidden && toggle.checked) { tick(); } });
    schedule();
})();
</script>
@endsection
