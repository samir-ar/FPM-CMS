<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Poll results</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
@verbatim
<style>
    /* Same look as the council results board (party blue, logo mark, glass header cards). The charts sit on light
       "paper" cards so the colours keep their meaning and the page prints cleanly. Static page, no animation. */
    :root {
        --bg1: #004a76; --bg2: #004e7c;
        --glass: rgba(0, 24, 42, .14); --glass-line: rgba(247, 245, 240, .2);
        --text: #f7f5f0; --muted: #b3c9d9; --gold: #f48d24; --radius: 22px;

        --surface: #fcfcfb; --ink: #0b0b0b; --ink-2: #52514e; --ink-3: #85847f; --grid: #e6e5e0; --track: #efeee9;
        --members: #34495e; --guests: #aab7c4; --timeline: #1f86bd;
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    html { min-height: 100%; background: #004a76; }
    body {
        min-height: 100vh; font-family: 'Inter', 'Tajawal', 'Segoe UI', system-ui, sans-serif; color: var(--text);
        background: radial-gradient(1000px 800px at 105% 10%, rgba(0, 78, 124, .6), transparent 55%), linear-gradient(160deg, var(--bg2), var(--bg1) 70%);
        background-attachment: fixed; font-variant-numeric: tabular-nums;
    }
    bdi, .auto { unicode-bidi: plaintext; }

    header { display: flex; align-items: center; justify-content: space-between; gap: 24px; flex-wrap: wrap; padding: clamp(14px, 1.6vw, 26px) clamp(18px, 2.4vw, 44px); }
    .brand { display: flex; align-items: center; gap: 18px; min-width: 0; }
    .mark { flex: none; width: 72px; height: 72px; border-radius: 18px; overflow: hidden; background: #004e7c; box-shadow: 0 10px 30px rgba(0,0,0,.4), 0 0 0 3px rgba(247,245,240,.55); }
    .mark img { display: block; width: 100%; height: 100%; transform: scale(1.35); }
    .kicker { font-size: 12px; font-weight: 600; letter-spacing: .32em; text-transform: uppercase; color: var(--gold); }
    h1 { font-size: clamp(20px, 2.2vw, 34px); font-weight: 800; line-height: 1.25; margin-top: 4px; }
    .sub { color: var(--muted); font-size: 14px; margin-top: 6px; }
    .tools { display: flex; gap: 10px; flex-wrap: wrap; }
    .btn { cursor: pointer; text-decoration: none; color: var(--text); font: 600 13px 'Inter', sans-serif; padding: 10px 16px; border-radius: 12px; background: rgba(0,24,42,.24); border: 1px solid var(--glass-line); }
    .btn:hover { background: rgba(255,255,255,.16); }

    main { display: grid; gap: clamp(14px, 1.3vw, 22px); padding: 0 clamp(18px, 2.4vw, 44px) clamp(24px, 3vw, 48px); max-width: 1240px; margin: 0 auto; }

    .kpis { display: grid; grid-template-columns: repeat(4, 1fr); gap: clamp(12px, 1.2vw, 20px); }
    .kpi { background: var(--glass); border: 1px solid var(--glass-line); border-radius: var(--radius); padding: 18px 22px; }
    .kpi .l { font-size: 11px; font-weight: 700; letter-spacing: .22em; text-transform: uppercase; color: var(--muted); }
    .kpi .v { font-size: clamp(28px, 3vw, 46px); font-weight: 800; line-height: 1.1; margin-top: 8px; }
    .kpi .v.small { font-size: clamp(17px, 1.6vw, 24px); line-height: 1.3; }
    .kpi .n { font-size: 13px; color: var(--muted); margin-top: 6px; }
    .kpi.hero { border-color: rgba(244,141,36,.55); }

    .paper { background: var(--surface); color: var(--ink); border-radius: var(--radius); padding: clamp(18px, 1.8vw, 30px); box-shadow: 0 24px 60px rgba(0,0,0,.25); }
    .paper h2 { font-size: 13px; font-weight: 700; letter-spacing: .14em; text-transform: uppercase; color: var(--ink-2); margin-bottom: 4px; }
    .paper .hint { font-size: 13px; color: var(--ink-3); margin-bottom: 20px; }
    .grid2 { display: grid; grid-template-columns: minmax(260px, 340px) 1fr; gap: clamp(14px, 1.3vw, 22px); align-items: stretch; }

    /* donut */
    .donut-wrap { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 16px; }
    .donut { width: 100%; max-width: 240px; height: auto; }
    .donut text { font-family: 'Inter', sans-serif; }
    .legend { display: grid; gap: 8px; width: 100%; font-size: 14px; }
    .legend div { display: flex; align-items: center; gap: 10px; }
    .legend i { flex: none; width: 12px; height: 12px; border-radius: 3px; }
    .legend span { flex: 1; min-width: 0; overflow-wrap: anywhere; }
    .legend b { font-weight: 700; }

    /* option bars */
    .bars { display: grid; gap: 18px; }
    .bar-row .top { display: flex; justify-content: space-between; align-items: baseline; gap: 16px; margin-bottom: 7px; }
    .bar-row .name { font-size: 15px; font-weight: 600; overflow-wrap: anywhere; }
    .bar-row .name small { font-weight: 500; color: var(--ink-3); font-size: 12px; margin-inline-start: 6px; }
    .bar-row .val { flex: none; font-size: 14px; color: var(--ink-2); }
    .bar-row .val b { font-size: 17px; color: var(--ink); font-weight: 800; }
    .track { height: 18px; background: var(--track); border-radius: 9px; overflow: hidden; }
    .fill { height: 100%; border-radius: 0 9px 9px 0; min-width: 0; }
    .fill.zero { min-width: 0; }

    /* members vs guests */
    .split { display: flex; height: 40px; border-radius: 10px; overflow: hidden; gap: 2px; background: var(--surface); }
    .split div { display: flex; align-items: center; padding: 0 12px; font-size: 13px; font-weight: 700; white-space: nowrap; }
    .split .m { background: var(--members); color: #fff; }
    .split .g { background: var(--guests); color: #0b0b0b; }
    .key { display: flex; gap: 22px; margin-top: 10px; font-size: 13px; color: var(--ink-2); flex-wrap: wrap; }
    .key i { display: inline-block; width: 11px; height: 11px; border-radius: 3px; margin-inline-end: 7px; vertical-align: -1px; }

    table.t { width: 100%; border-collapse: collapse; font-size: 14px; margin-top: 22px; }
    table.t th { text-align: left; font-size: 11px; letter-spacing: .14em; text-transform: uppercase; color: var(--ink-3); font-weight: 700; padding: 8px 10px; border-bottom: 1px solid var(--grid); }
    table.t td { padding: 10px; border-bottom: 1px solid var(--grid); }
    table.t th.r, table.t td.r { text-align: right; }
    table.t i { display: inline-block; width: 11px; height: 11px; border-radius: 3px; margin-inline-end: 8px; vertical-align: -1px; }
    table.t tr:last-child td { border-bottom: 0; font-weight: 700; }

    .timeline { width: 100%; height: auto; display: block; }
    .timeline text { font-family: 'Inter', sans-serif; fill: var(--ink-3); font-size: 11px; }
    .empty { color: var(--ink-3); font-size: 14px; padding: 18px 0; }
    footer { color: var(--muted); font-size: 12.5px; text-align: center; padding: 4px 10px 0; line-height: 1.7; }

    @media (max-width: 900px) { .kpis { grid-template-columns: repeat(2, 1fr); } .grid2 { grid-template-columns: 1fr; } }
    @media print {
        html, body { background: #fff !important; color: #000; }
        .tools { display: none; }
        header h1, .sub, .kicker, footer { color: #000; }
        .kpi { background: #fff; border-color: #ccc; } .kpi .l, .kpi .n { color: #555; }
        .paper { box-shadow: none; border: 1px solid #ddd; }
        .mark { box-shadow: none; }
    }
</style>
@endverbatim
</head>
<body>
@php
    $statusText = !$poll->show ? 'Switched off in the CMS' : ($closed ? 'Closed' : 'Open');
    $untilText = $expiry ? ($closed ? 'Ended ' : 'Ends ') . $expiry->format('d M Y, H:i') : 'No end date';
    $r = 78; $C = 2 * M_PI * $r;
@endphp
<header>
    <div class="brand">
        <div class="mark"><img src="{{ asset('council-board/fpm-logo.png') }}" alt=""></div>
        <div>
            <div class="kicker">Poll results</div>
            <h1 class="auto">{{ $question }}</h1>
            <div class="sub">Poll #{{ $poll->id }} &middot; {{ $statusText }} &middot; {{ $untilText }} &middot; {{ $poll->show_for_guest ? 'Guests allowed' : 'Members only' }}</div>
        </div>
    </div>
    <div class="tools">
        <a class="btn" href="{{ route('admin.polls.index') }}">&larr; Polls</a>
        <a class="btn" href="{{ route('admin.userPolls.index', ['poll_id' => $poll->id]) }}">Answers list</a>
        <a class="btn" href="#" onclick="window.print(); return false;">Print</a>
    </div>
</header>

<main>
    <div class="kpis">
        <div class="kpi hero"><div class="l">Total voters</div><div class="v">{{ number_format($total) }}</div><div class="n">one answer per member or phone</div></div>
        <div class="kpi"><div class="l">Members</div><div class="v">{{ number_format($memberTotal) }}</div><div class="n">{{ $total ? round($memberTotal * 100 / $total) : 0 }}% of answers</div></div>
        <div class="kpi"><div class="l">Guests</div><div class="v">{{ number_format($guestTotal) }}</div><div class="n">{{ $total ? round($guestTotal * 100 / $total) : 0 }}% of answers</div></div>
        <div class="kpi">
            <div class="l">{{ count($leaders) > 1 ? 'Tied in the lead' : 'Leading option' }}</div>
            @if (count($leaders))
                <div class="v small"><bdi>{{ collect($leaders)->pluck('text')->implode(' / ') }}</bdi></div>
                <div class="n">{{ $leaders[0]['pct'] }}% &middot; {{ number_format($leaders[0]['count']) }} {{ $leaders[0]['count'] === 1 ? 'vote' : 'votes' }}</div>
            @else
                <div class="v small">No answers yet</div>
            @endif
        </div>
    </div>

    <div class="grid2">
        {{-- share of answers: donut --}}
        <section class="paper">
            <h2>Share of answers</h2>
            <div class="hint">Each option's part of all answers</div>
            <div class="donut-wrap">
                @if ($total > 0)
                    <svg class="donut" viewBox="0 0 200 200" role="img" aria-label="Share of answers per option">
                        <g transform="rotate(-90 100 100)">
                            <circle cx="100" cy="100" r="{{ $r }}" fill="none" stroke="#efeee9" stroke-width="30"/>
                            @php $offset = 0; @endphp
                            @foreach ($rows as $row)
                                @if ($row['count'] > 0)
                                    @php $len = $row['count'] / $total * $C; @endphp
                                    <circle cx="100" cy="100" r="{{ $r }}" fill="none" stroke="{{ $row['color'] }}" stroke-width="30"
                                            stroke-dasharray="{{ max($len - 2, 0.5) }} {{ $C }}" stroke-dashoffset="{{ -$offset }}">
                                        <title>{{ $row['text'] }}: {{ $row['count'] }} ({{ $row['pct'] }}%)</title>
                                    </circle>
                                    @php $offset += $len; @endphp
                                @endif
                            @endforeach
                        </g>
                        <text x="100" y="98" text-anchor="middle" font-size="34" font-weight="800" fill="#0b0b0b">{{ number_format($total) }}</text>
                        <text x="100" y="120" text-anchor="middle" font-size="12" fill="#85847f">{{ $total === 1 ? 'answer' : 'answers' }}</text>
                    </svg>
                    <div class="legend">
                        @foreach ($rows as $row)
                            <div><i style="background: {{ $row['color'] }}"></i><span><bdi>{{ $row['text'] }}</bdi></span><b>{{ $row['pct'] }}%</b></div>
                        @endforeach
                    </div>
                @else
                    <div class="empty">No answers yet.</div>
                @endif
            </div>
        </section>

        {{-- answers per option: bars --}}
        <section class="paper">
            <h2>Answers per option</h2>
            <div class="hint">Largest first. Each option keeps its colour in every chart.</div>
            <div class="bars">
                @foreach ($rows as $row)
                    <div class="bar-row">
                        <div class="top">
                            <div class="name"><bdi>{{ $row['text'] }}</bdi>@if ($row['removed'])<small>(option removed)</small>@endif</div>
                            <div class="val"><b>{{ number_format($row['count']) }}</b> &nbsp;{{ $row['pct'] }}%</div>
                        </div>
                        <div class="track" title="{{ $row['text'] }}: {{ $row['count'] }} ({{ $row['pct'] }}%)">
                            <div class="fill" style="width: {{ $row['pct'] }}%; background: {{ $row['color'] }}"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    </div>

    {{-- members vs guests --}}
    <section class="paper">
        <h2>Who answered</h2>
        <div class="hint">Members (logged in) and guests (identified by the phone), overall and per option</div>
        @if ($total > 0)
            <div class="split">
                @if ($memberTotal > 0)<div class="m" style="flex: {{ $memberTotal }}">Members {{ number_format($memberTotal) }}</div>@endif
                @if ($guestTotal > 0)<div class="g" style="flex: {{ $guestTotal }}">Guests {{ number_format($guestTotal) }}</div>@endif
            </div>
            <div class="key"><span><i style="background: var(--members)"></i>Members</span><span><i style="background: var(--guests)"></i>Guests</span></div>
            <table class="t">
                <thead><tr><th>Option</th><th class="r">Members</th><th class="r">Guests</th><th class="r">Total</th><th class="r">Share</th></tr></thead>
                <tbody>
                @foreach ($rows as $row)
                    <tr>
                        <td><i style="background: {{ $row['color'] }}"></i><bdi>{{ $row['text'] }}</bdi></td>
                        <td class="r">{{ number_format($row['members']) }}</td>
                        <td class="r">{{ number_format($row['guests']) }}</td>
                        <td class="r">{{ number_format($row['count']) }}</td>
                        <td class="r">{{ $row['pct'] }}%</td>
                    </tr>
                @endforeach
                    <tr><td>Total</td><td class="r">{{ number_format($memberTotal) }}</td><td class="r">{{ number_format($guestTotal) }}</td><td class="r">{{ number_format($total) }}</td><td class="r">100%</td></tr>
                </tbody>
            </table>
        @else
            <div class="empty">No answers yet.</div>
        @endif
    </section>

    {{-- when the answers came in --}}
    <section class="paper">
        <h2>Answers over time</h2>
        <div class="hint">Answers per {{ $timeline['unit'] }}</div>
        @php
            $b = $timeline['buckets']; $n = count($b);
            $W = 880; $H = 230; $pl = 40; $pr = 10; $pt = 12; $pb = 34;
            $max = $n ? max(array_column($b, 'count')) : 0;
            $step = $max <= 4 ? 1 : (int) ceil($max / 4);
            $top = max($step * (int) ceil($max / max($step, 1)), 1);
            $iw = $W - $pl - $pr; $ih = $H - $pt - $pb;
            $slot = $n ? $iw / $n : 0; $bw = $n ? min(max($slot * 0.7, 2), 46) : 0;
            $every = $n > 12 ? (int) ceil($n / 10) : 1;
        @endphp
        @if ($n)
            <svg class="timeline" viewBox="0 0 {{ $W }} {{ $H }}" role="img" aria-label="Answers over time">
                @for ($g = 0; $g <= $top; $g += $step)
                    @php $y = $pt + $ih - ($g / $top) * $ih; @endphp
                    <line x1="{{ $pl }}" x2="{{ $W - $pr }}" y1="{{ $y }}" y2="{{ $y }}" stroke="#e6e5e0" stroke-width="1"/>
                    <text x="{{ $pl - 8 }}" y="{{ $y + 4 }}" text-anchor="end">{{ $g }}</text>
                @endfor
                @foreach ($b as $i => $bucket)
                    @php
                        $h = $top ? ($bucket['count'] / $top) * $ih : 0;
                        $x = $pl + $slot * $i + ($slot - $bw) / 2;
                    @endphp
                    @if ($bucket['count'] > 0)
                        <path d="M {{ $x }} {{ $pt + $ih }} v {{ -max($h - 3, 0) }} q 0 -3 3 -3 h {{ max($bw - 6, 0) }} q 3 0 3 3 v {{ max($h - 3, 0) }} z" fill="var(--timeline)">
                            <title>{{ $bucket['short'] }}: {{ $bucket['count'] }}</title>
                        </path>
                    @endif
                    @if ($i % $every === 0)
                        <text x="{{ $x + $bw / 2 }}" y="{{ $H - 12 }}" text-anchor="middle">{{ $bucket['short'] }}</text>
                    @endif
                @endforeach
                <line x1="{{ $pl }}" x2="{{ $W - $pr }}" y1="{{ $pt + $ih }}" y2="{{ $pt + $ih }}" stroke="#bdbcb5" stroke-width="1"/>
            </svg>
        @else
            <div class="empty">No answers yet.</div>
        @endif
    </section>

    <footer>
        Counts every saved answer of this poll (members and guests), the same numbers as the "Answers list".<br>
        Generated {{ $generatedAt->format('d M Y, H:i') }} &middot; static snapshot, reload the page to refresh.
    </footer>
</main>
</body>
</html>
