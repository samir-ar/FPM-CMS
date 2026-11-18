@extends($layout)

@section('content')
<style>
    /* Results page. One accent (the party orange) for winners / the leader, quiet grey for everything else;
       hairlines instead of boxes; no gradients, no shadows. Scoped with .cr- so it cannot touch the rest of the CMS. */
    .cr { --ink:#1d2329; --ink-2:#5b6670; --ink-3:#8b949c; --line:#e6e9ec; --track:#f1f3f5; --bar:#c3cad1; --accent:#d9531e; --accent-soft:#fbeee8; --ok:#2f7d4f;
          color:var(--ink); font-feature-settings:"tnum" 1; }
    .cr * { box-sizing:border-box; }
    .cr-card { background:#fff; border:1px solid var(--line); border-radius:8px; margin-bottom:22px; }
    .cr-card > header { display:flex; align-items:baseline; justify-content:space-between; gap:12px; padding:16px 22px; border-bottom:1px solid var(--line); }
    .cr-card h2 { font-size:19px; font-weight:600; margin:0; line-height:1.3; }
    .cr-card h3 { font-size:15px; font-weight:600; margin:0 0 2px; }
    .cr-sub { color:var(--ink-2); font-size:12.5px; }
    .cr-body { padding:18px 22px 6px; }

    /* picker: pill toggles */
    .cr-chips { display:flex; flex-wrap:wrap; gap:8px; }
    .cr-chip { position:relative; margin:0; }
    .cr-chip input { position:absolute; opacity:0; inset:0; cursor:pointer; }
    .cr-chip span { display:inline-block; padding:7px 14px; border:1px solid var(--line); border-radius:999px; font-size:13px; font-weight:500; color:var(--ink-2); background:#fff; transition:background .12s, border-color .12s, color .12s; }
    .cr-chip small { color:var(--ink-3); font-weight:400; margin-inline-start:6px; }
    .cr-chip:hover span { border-color:#c9d0d6; }
    .cr-chip input:checked + span { background:var(--ink); border-color:var(--ink); color:#fff; }
    .cr-chip input:checked + span small { color:#c4cbd1; }
    .cr-chip input:focus-visible + span { outline:2px solid var(--accent); outline-offset:2px; }
    .cr-tools { display:flex; flex-wrap:wrap; align-items:center; gap:18px; margin-top:16px; padding-top:14px; border-top:1px solid var(--line); }
    .cr-tools label { margin:0; font-weight:500; color:var(--ink-2); font-size:13px; }
    .cr-tools input[type=number] { width:70px; height:32px; border:1px solid var(--line); border-radius:6px; padding:0 8px; margin-inline-start:6px; }
    .cr-link { font-size:12.5px; color:var(--ink-2); text-decoration:underline; text-underline-offset:3px; margin-inline-end:12px; }
    .cr-btn { height:34px; padding:0 18px; border-radius:6px; border:1px solid var(--ink); background:var(--ink); color:#fff; font-weight:500; font-size:13px; cursor:pointer; }
    .cr-btn.ghost { background:#fff; color:var(--ink); border-color:var(--line); }

    /* headline numbers: a quiet row, turnout as a meter */
    .cr-stats { display:grid; grid-template-columns:1.6fr 1fr 1fr 1fr; gap:0; border-bottom:1px solid var(--line); }
    .cr-stat { padding:16px 22px; border-inline-end:1px solid var(--line); }
    .cr-stat:last-child { border-inline-end:0; }
    .cr-stat .l { color:var(--ink-2); font-size:12px; margin-bottom:4px; }
    .cr-stat .n { font-size:26px; font-weight:600; line-height:1.1; }
    .cr-stat .n.hero { font-size:40px; }
    .cr-stat .s { color:var(--ink-3); font-size:12px; margin-top:3px; }
    .cr-meter { height:6px; background:var(--track); border-radius:3px; margin-top:10px; overflow:hidden; }
    .cr-meter i { display:block; height:100%; background:var(--accent); border-radius:3px; }

    /* ranked bars */
    .cr-q { padding:20px 22px 8px; }
    .cr-q + .cr-q { border-top:1px solid var(--line); }
    .cr-q-head { display:flex; justify-content:space-between; align-items:baseline; gap:12px; margin-bottom:12px; }
    .cr-rows { list-style:none; margin:0; padding:0; }
    .cr-row { display:grid; grid-template-columns:30px minmax(120px,260px) 1fr 150px; align-items:center; gap:12px; padding:5px 0; }
    .cr-rank { color:var(--ink-3); font-size:13px; text-align:end; }
    .cr-name { font-size:14px; line-height:1.35; text-align:left; unicode-bidi:plaintext; }
    .cr-track { position:relative; height:20px; background:var(--track); border-radius:0 4px 4px 0; }
    .cr-fill { position:absolute; inset-block:0; inset-inline-start:0; background:var(--bar); border-radius:0 4px 4px 0; min-width:2px; }
    .cr-row.lead .cr-fill, .cr-row.win .cr-fill { background:var(--accent); }
    .cr-row.win .cr-name, .cr-row.lead .cr-name { font-weight:600; }
    .cr-num { font-size:13px; color:var(--ink-2); text-align:end; white-space:nowrap; }
    .cr-num b { color:var(--ink); font-weight:600; font-size:14px; }
    .cr-cut { display:flex; align-items:center; gap:10px; margin:6px 0; color:var(--accent); font-size:11.5px; font-weight:600; letter-spacing:.04em; text-transform:uppercase; }
    .cr-cut:before, .cr-cut:after { content:""; height:1px; background:var(--accent); opacity:.45; flex:1; }
    .cr-note { background:var(--accent-soft); color:#8a3412; border-radius:6px; padding:8px 12px; font-size:12.5px; margin:0 0 12px; }
    .cr-empty { color:var(--ink-3); padding:18px 22px; font-size:13px; }
    .cr-tip { position:fixed; z-index:9999; pointer-events:none; background:var(--ink); color:#fff; padding:7px 10px; border-radius:6px; font-size:12px; line-height:1.5; display:none; max-width:260px; }
    .cr details { padding:0 22px 16px; }
    .cr details summary { cursor:pointer; color:var(--ink-2); font-size:12.5px; padding:6px 0; }
    .cr details table { width:100%; font-size:13px; margin-top:6px; }
    .cr details th { color:var(--ink-3); font-weight:500; font-size:12px; border-bottom:1px solid var(--line); padding:5px 8px; }
    .cr details td { padding:5px 8px; border-bottom:1px solid var(--line); }

    @media (max-width:900px) {
        .cr-stats { grid-template-columns:1fr 1fr; }
        .cr-stat { border-bottom:1px solid var(--line); }
        .cr-row { grid-template-columns:26px 1fr 110px; }
        .cr-track { grid-column:2 / 4; grid-row:2; }
        .cr-row .cr-num { grid-column:3; grid-row:1; }
    }
    @media print {
        .no-print { display:none !important; }
        .cr-card { break-inside:avoid; border-color:#ccc; }
        .cr-row.lead .cr-fill, .cr-row.win .cr-fill { background:#d9531e !important; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
        .cr-fill { -webkit-print-color-adjust:exact; print-color-adjust:exact; }
    }
</style>

<div class="cr">

    <div class="cr-card no-print">
        <header>
            <div>
                <h2>National Council results</h2>
                <div class="cr-sub">Choose one poll, or several, to see their results together.</div>
            </div>
            <a href="{{ route('admin.national-council-poll.index') }}" class="cr-link" style="margin:0;">Back to polls</a>
        </header>
        <form method="GET" action="{{ route('admin.national-council-poll.results-overview') }}">
            <div class="cr-body" style="padding-bottom:18px;">
                <div style="margin-bottom:10px;">
                    <a href="#" id="pick-all" class="cr-link">Select all</a><a href="#" id="pick-none" class="cr-link">Clear</a>
                </div>
                <div class="cr-chips">
                    @forelse($polls as $p)
                        <label class="cr-chip">
                            <input type="checkbox" name="polls[]" value="{{ $p->id }}" @if(in_array($p->id, $chosen)) checked @endif>
                            <span><bdi>{{ $p->title }}</bdi><small>
                                {{ $p->is_published ? 'published' : 'unpublished' }}@if($p->closes_at && \Carbon\Carbon::parse($p->closes_at)->isPast()), closed @endif
                            </small></span>
                        </label>
                    @empty
                        <div class="cr-sub">There are no polls yet.</div>
                    @endforelse
                </div>
                <div class="cr-tools">
                    <label>Mark the top <input type="number" name="top" min="0" value="{{ $top ?: '' }}" placeholder="6"> <span class="cr-sub">(seats to fill; leave empty for a single winner)</span></label>
                    <span style="flex:1"></span>
                    @if(count($sections))
                        <button type="button" class="cr-btn ghost" onclick="window.print()">Print</button>
                    @endif
                    <button type="submit" class="cr-btn">Show results</button>
                </div>
            </div>
        </form>
    </div>

    @if(!count($sections) && count($chosen))
        <div class="cr-card"><div class="cr-empty">None of the chosen polls could be found.</div></div>
    @endif

    @foreach($sections as $s)
        @php
            $poll = $s['poll'];
            $turnout = $s['electorsCount'] ? round($s['votersCount'] * 100 / $s['electorsCount'], 1) : 0;
        @endphp
        <section class="cr-card">
            <header>
                <div>
                    <h2 dir="auto">{{ $poll->title }}</h2>
                    <div class="cr-sub">
                        {{ $poll->is_published ? 'Published' : 'Not published' }}@if($poll->closes_at), closes {{ \Carbon\Carbon::parse($poll->closes_at)->format('d M Y H:i') }}@endif
                    </div>
                </div>
                <a href="{{ route('admin.national-council-poll.results', ['id' => $poll->id, 'top' => $top ?: null]) }}" target="_blank" class="cr-link no-print" style="margin:0;">Full report</a>
            </header>

            <div class="cr-stats">
                <div class="cr-stat">
                    <div class="l">Turnout</div>
                    <div class="n hero">{{ $turnout }}<span style="font-size:20px;color:var(--ink-2)">%</span></div>
                    <div class="cr-meter"><i style="width:{{ min(100, $turnout) }}%"></i></div>
                </div>
                <div class="cr-stat"><div class="l">Voted</div><div class="n">{{ $s['votersCount'] }}</div><div class="s">of {{ $s['electorsCount'] }} allowed</div></div>
                <div class="cr-stat"><div class="l">Weight that voted</div><div class="n">{{ $s['votedWeight'] }}</div><div class="s">of {{ $s['totalWeight'] }}</div></div>
                <div class="cr-stat"><div class="l">Questions</div><div class="n">{{ $poll->questions->count() }}</div></div>
            </div>

            @forelse($poll->questions as $question)
                @php
                    $r = $s['ranking'][$question->id];
                    $max = max(1, (int) $r['rows']->max('weight'));
                    $hasVotes = $r['cast_weight'] > 0;
                @endphp
                <div class="cr-q">
                    <div class="cr-q-head">
                        <h3 dir="auto">{{ $question->question }}</h3>
                        <span class="cr-sub">weight cast {{ $r['cast_weight'] }}</span>
                    </div>

                    @if($r['tie_at_cutoff'])
                        <p class="cr-note">Tie in weight at the cut-off (place {{ $top }} and the next one). A decision is needed.</p>
                    @endif

                    <ol class="cr-rows">
                        @foreach($r['rows'] as $i => $row)
                            @php
                                $isWin = $top && $row['rank'] <= $top && $row['weight'] > 0;
                                $isLead = !$top && $hasVotes && $row['rank'] === 1;
                            @endphp
                            <li class="cr-row {{ $isWin ? 'win' : '' }} {{ $isLead ? 'lead' : '' }}"
                                data-tip="{{ $row['answer'] }}&#10;Weight {{ $row['weight'] }} &middot; {{ $row['votes'] }} votes &middot; {{ $row['pct'] }}% of the weight">
                                <span class="cr-rank">{{ $row['rank'] }}</span>
                                <span class="cr-name" dir="auto">{{ $row['answer'] }}</span>
                                <span class="cr-track"><span class="cr-fill" style="width:{{ round($row['weight'] * 100 / $max, 2) }}%"></span></span>
                                <span class="cr-num"><b>{{ $row['weight'] }}</b> &nbsp;{{ $row['votes'] }} {{ $row['votes'] == 1 ? 'vote' : 'votes' }} &nbsp;{{ $row['pct'] }}%</span>
                            </li>
                            @if($top && $row['rank'] <= $top && isset($r['rows'][$i + 1]) && $r['rows'][$i + 1]['rank'] > $top)
                                <li class="cr-cut" aria-label="cut-off"><span>Cut-off, top {{ $top }}</span></li>
                            @endif
                        @endforeach
                    </ol>
                    @unless($hasVotes)
                        <div class="cr-sub" style="padding:4px 0 10px;">No votes yet for this question.</div>
                    @endunless
                </div>

                <details>
                    <summary>Show as a table</summary>
                    <table>
                        <thead><tr><th>#</th><th>Option / candidate</th><th>Weight</th><th>Votes</th><th>% of weight</th></tr></thead>
                        <tbody>
                        @foreach($r['rows'] as $row)
                            <tr><td>{{ $row['rank'] }}</td><td dir="auto">{{ $row['answer'] }}</td><td>{{ $row['weight'] }}</td><td>{{ $row['votes'] }}</td><td>{{ $row['pct'] }}%</td></tr>
                        @endforeach
                        </tbody>
                    </table>
                </details>
            @empty
                <div class="cr-empty">This poll has no questions yet.</div>
            @endforelse
        </section>
    @endforeach

    <div class="cr-tip" id="cr-tip"></div>
</div>

<script>
    (function () {
        function setAll(v) { document.querySelectorAll('input[name="polls[]"]').forEach(function (c) { c.checked = v; }); }
        var a = document.getElementById('pick-all'), n = document.getElementById('pick-none');
        if (a) a.addEventListener('click', function (e) { e.preventDefault(); setAll(true); });
        if (n) n.addEventListener('click', function (e) { e.preventDefault(); setAll(false); });

        // hover tooltip on every bar row
        var tip = document.getElementById('cr-tip');
        document.querySelectorAll('.cr-row').forEach(function (row) {
            row.addEventListener('mousemove', function (e) {
                tip.textContent = row.getAttribute('data-tip');
                tip.style.whiteSpace = 'pre-line';
                tip.style.display = 'block';
                tip.style.left = Math.min(e.clientX + 14, window.innerWidth - tip.offsetWidth - 10) + 'px';
                tip.style.top = (e.clientY + 16) + 'px';
            });
            row.addEventListener('mouseleave', function () { tip.style.display = 'none'; });
        });
    })();
</script>
@endsection
