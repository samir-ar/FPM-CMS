<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Live voting - {{ $poll->title }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
@verbatim
<style>
    :root {
        /* the party logo's colours: blue #004E7C, orange #F48D24, with a warm off-white */
        --bg1: #004a76;
        --bg2: #004e7c;
        --card: rgba(0, 24, 42, .14);          /* panels are a touch darker than the page, never lighter: keeps the blue true */
        --card-strong: rgba(0, 24, 42, .24);
        --line: rgba(247, 245, 240, .2);
        --text: #f7f5f0;
        --muted: #b3c9d9;
        --orange: #f48d24;
        --gold: #f48d24;
        --blue: #1f86bd;
        --green: #34e0a0;
        --red: #ff7a7a;
        --radius: 22px;
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    html, body { min-height: 100%; }
    body {
        font-family: 'Inter', 'Tajawal', 'Segoe UI', system-ui, sans-serif;
        color: var(--text);
        background:
            radial-gradient(1200px 700px at 12% -10%, rgba(244, 141, 36, .06), transparent 60%),
            radial-gradient(1000px 800px at 105% 10%, rgba(0, 78, 124, .6), transparent 55%),
            linear-gradient(160deg, var(--bg2), var(--bg1) 70%);
        background-attachment: fixed;
        overflow-x: hidden;
    }
    /* fine dotted grid, like a control-room screen */
    body::before {
        content: ''; position: fixed; inset: 0; pointer-events: none; opacity: .5;
        background-image: radial-gradient(rgba(255,255,255,.07) 1px, transparent 1px);
        background-size: 28px 28px;
        mask-image: radial-gradient(ellipse at center, #000 30%, transparent 80%);
    }
    bdi, .auto { unicode-bidi: plaintext; }
    .num { font-variant-numeric: tabular-nums; }

    /* ---------- header ---------- */
    header {
        position: relative; z-index: 2;
        display: flex; align-items: center; justify-content: space-between; gap: 24px;
        padding: clamp(14px, 1.6vw, 26px) clamp(18px, 2.4vw, 44px);
    }
    .brand { display: flex; align-items: center; gap: 18px; min-width: 0; }
    /* the party logo: blue square with the orange check, top left */
    .mark { flex: none; width: 76px; height: 76px; border-radius: 18px; overflow: hidden; background: #004e7c;
            box-shadow: 0 10px 30px rgba(0, 0, 0, .4), 0 0 0 3px rgba(247, 245, 240, .55); }
    .mark img { display: block; width: 100%; height: 100%; transform: scale(1.35); }
    .kicker { font-size: 12px; font-weight: 600; letter-spacing: .32em; text-transform: uppercase; color: var(--gold); }
    h1 { font-size: clamp(22px, 2.4vw, 40px); font-weight: 800; line-height: 1.1; margin-top: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .sub { color: var(--muted); font-size: 14px; margin-top: 4px; }
    .tools { display: flex; align-items: center; gap: 12px; flex: none; }
    .live {
        display: inline-flex; align-items: center; gap: 9px; padding: 9px 16px; border-radius: 999px;
        background: rgba(52, 224, 160, .12); border: 1px solid rgba(52, 224, 160, .35);
        font-size: 13px; font-weight: 700; letter-spacing: .14em; color: var(--green);
    }
    .live i { width: 9px; height: 9px; border-radius: 50%; background: var(--green); box-shadow: 0 0 0 0 rgba(52,224,160,.7); animation: ping 1.8s infinite; }
    .live.closed { background: rgba(255,255,255,.07); border-color: var(--line); color: var(--muted); }
    .live.closed i { background: var(--muted); animation: none; }
    .live.lost { background: rgba(255,122,122,.12); border-color: rgba(255,122,122,.4); color: var(--red); }
    .live.lost i { background: var(--red); }
    @keyframes ping { 0% { box-shadow: 0 0 0 0 rgba(52,224,160,.6); } 70% { box-shadow: 0 0 0 12px rgba(52,224,160,0); } 100% { box-shadow: 0 0 0 0 rgba(52,224,160,0); } }
    #clock { font-size: 22px; font-weight: 600; letter-spacing: .04em; min-width: 98px; text-align: right; }
    .btn {
        cursor: pointer; text-decoration: none; color: var(--text); font: 600 13px 'Inter', sans-serif;
        padding: 10px 16px; border-radius: 12px; background: var(--card-strong); border: 1px solid var(--line);
        transition: background .2s, transform .2s;
    }
    .btn:hover { background: rgba(255,255,255,.16); transform: translateY(-1px); }

    /* ---------- layout ---------- */
    main {
        position: relative; z-index: 1;
        display: grid; gap: clamp(14px, 1.3vw, 22px);
        grid-template-columns: repeat(12, 1fr);
        grid-template-rows: auto auto auto 1fr;
        padding: 0 clamp(18px, 2.4vw, 44px) clamp(18px, 2vw, 32px);
    }
    .card {
        background: var(--card); border: 1px solid var(--line); border-radius: var(--radius);
        backdrop-filter: blur(14px); -webkit-backdrop-filter: blur(14px);
        padding: clamp(16px, 1.5vw, 26px);
        box-shadow: 0 24px 60px rgba(0,0,0,.28), inset 0 1px 0 rgba(255,255,255,.06);
    }
    .card h2 { font-size: 12px; font-weight: 700; letter-spacing: .24em; text-transform: uppercase; color: var(--muted); margin-bottom: 16px; display: flex; justify-content: space-between; align-items: center; }
    .hero { grid-column: span 5; grid-row: span 2; display: flex; flex-direction: column; align-items: center; }
    .kpis { grid-column: span 7; align-self: start; display: grid; grid-template-columns: repeat(4, 1fr); gap: clamp(10px, 1vw, 16px); }
    .groups { grid-column: span 7; }
    .seats { grid-column: span 10; display: flex; flex-direction: column; }
    .seats #seatMap { flex: 1; justify-content: space-evenly; }
    .ticker { grid-column: span 2; padding-left: 14px; padding-right: 14px; }
    #recent { min-width: 0; }

    /* ---------- the gauge ---------- */
    .gauge { position: relative; width: min(100%, 270px); aspect-ratio: 1; margin: 2px auto 6px; }
    .gauge svg { width: 100%; height: 100%; overflow: visible; }
    .track { fill: none; stroke: rgba(255,255,255,.08); stroke-width: 26; }
    .arc { fill: none; stroke-width: 26; stroke-linecap: round; transition: stroke-dashoffset 1.4s cubic-bezier(.2,.8,.2,1); }
    .arc.glow { filter: blur(11px); opacity: .55; }
    .tick { stroke: rgba(255,255,255,.55); stroke-width: 3; stroke-linecap: round; transition: stroke .5s; }
    .tick.hit { stroke: var(--green); }
    .tick-label { fill: #c9d8ea; font: 800 24px 'Inter', sans-serif; letter-spacing: .06em; text-anchor: middle; transition: fill .5s; }
    .tick-label.hit { fill: var(--green); }
    .gauge-center { position: absolute; inset: 0; display: grid; place-content: center; text-align: center; }
    .big { font-size: clamp(40px, 3.8vw, 68px); font-weight: 800; line-height: 1; letter-spacing: -.03em; background: linear-gradient(180deg, #fff, #ffd9a1); -webkit-background-clip: text; background-clip: text; color: transparent; }
    .big-sub { margin-top: 4px; font-size: 11px; letter-spacing: .22em; text-transform: uppercase; color: var(--muted); font-weight: 600; }
    .big-of { margin-top: 6px; font-size: 17px; font-weight: 600; color: var(--text); }
    .big-of b { color: var(--gold); font-weight: 700; }

    .milestones { width: 100%; display: grid; gap: 10px; margin-top: 4px; }
    .ms { padding: 11px 16px; border-radius: 16px; background: rgba(255,255,255,.045); border: 1px solid var(--line); transition: border-color .5s, background .5s; }
    .ms-top { display: flex; justify-content: space-between; align-items: baseline; gap: 10px; }
    .ms-name { font-size: 13px; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; color: var(--muted); }
    .ms-name b { color: var(--text); letter-spacing: 0; margin-left: 6px; font-size: 16px; }
    .ms-state { font-size: 15px; font-weight: 700; color: var(--gold); }
    .ms-bar { height: 8px; border-radius: 99px; background: rgba(255,255,255,.09); margin-top: 11px; overflow: hidden; }
    .ms-bar i, .grp-bar i { position: relative; overflow: hidden; }
    .ms-bar i::after, .grp-bar i::after { content: ''; position: absolute; inset: 0; background: linear-gradient(100deg, transparent 30%, rgba(255,255,255,.45) 50%, transparent 70%); transform: translateX(-100%); animation: sweep 3.2s ease-in-out infinite; }
    @keyframes sweep { 0%, 55% { transform: translateX(-100%); } 100% { transform: translateX(100%); } }
    .ms-bar i { display: block; height: 100%; width: 0; border-radius: 99px; background: var(--orange); transition: width 1.2s cubic-bezier(.2,.8,.2,1); }
    .ms.reached { background: rgba(52, 224, 160, .09); border-color: rgba(52, 224, 160, .45); }
    .ms.reached .ms-state { color: var(--green); }
    .ms.reached .ms-bar i { background: linear-gradient(90deg, #19c58a, var(--green)); }

    /* ---------- KPI cards ---------- */
    .kpi { position: relative; overflow: hidden; padding: clamp(14px, 1.2vw, 20px); border-radius: var(--radius); background: var(--card); border: 1px solid var(--line); box-shadow: 0 18px 40px rgba(0,0,0,.25), inset 0 1px 0 rgba(255,255,255,.06); }
    .kpi::after { content: ''; position: absolute; right: -30px; top: -30px; width: 110px; height: 110px; border-radius: 50%; background: var(--accent, var(--orange)); opacity: .16; filter: blur(8px); }
    .kpi .label { font-size: 11px; font-weight: 700; letter-spacing: .2em; text-transform: uppercase; color: var(--muted); }
    .kpi .value { display: inline-block; margin-top: 6px; font-size: clamp(26px, 2.4vw, 40px); font-weight: 800; line-height: 1; }
    .kpi .hint { display: inline-block; margin: 0 0 0 10px !important; }   /* the small caption sits beside the number, not under it */
    .kpi .value small { font-size: .42em; font-weight: 600; color: var(--muted); margin-left: 4px; }
    .kpi .hint { margin-top: 9px; font-size: 13px; color: var(--muted); }

    /* ---------- bars by sector ---------- */
    .grp { display: grid; grid-template-columns: minmax(110px, 190px) 1fr auto; gap: 18px; align-items: center; padding: 7px 0; }
    .grp-name { font-size: 18px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .grp-bar { height: 20px; border-radius: 99px; background: rgba(255,255,255,.08); overflow: hidden; }
    .grp-bar i { display: block; height: 100%; width: 0; border-radius: 99px; background: linear-gradient(90deg, var(--blue), #5fbbe6); transition: width 1.2s cubic-bezier(.2,.8,.2,1); }
    .grp-bar i.full { background: linear-gradient(90deg, #19c58a, var(--green)); }
    .grp-num { font-size: 18px; color: var(--muted); min-width: 86px; text-align: right; }
    .grp-num b { color: var(--text); font-weight: 700; }
    /* row = members count | role name | bar | weight */
    .grp, .grp-head { grid-template-columns: 90px 240px 1fr 120px; }
    .grp-head { display: grid; gap: 18px; padding: 0 0 4px; }
    .gh { font-size: 11px; font-weight: 700; letter-spacing: .2em; text-transform: uppercase; color: var(--muted); }
    .gh-w { text-align: right; }
    .grp-count { font-size: 18px; color: var(--muted); text-align: left; }
    .grp-count b { color: var(--text); font-weight: 700; }
    .grp-num { font-size: 18px; min-width: 0; }

    /* ---------- seat map ---------- */
    .legend { display: flex; gap: 16px; font-size: 11px; letter-spacing: .12em; }
    .legend span { display: inline-flex; align-items: center; gap: 7px; }
    .legend i { width: 12px; height: 12px; border-radius: 50%; }
    .legend .on { background: var(--orange); box-shadow: 0 0 10px rgba(247,142,30,.8); }
    .legend .off { border: 1.5px solid rgba(255,255,255,.3); }
    /* the seat map is a tree: one centred row per level, joined by a thin line */
    #seatMap { display: flex; flex-direction: column; align-items: stretch; gap: 20px; }
    .tier { position: relative; display: flex; flex-wrap: wrap; justify-content: center; gap: 12px 46px; }
    .tier + .tier::before { content: ''; position: absolute; top: -20px; left: 50%; height: 20px; width: 1px; background: linear-gradient(rgba(247,142,30,.0), rgba(247,142,30,.55)); }
    .tier.t0 .seat { width: 72px; height: 72px; }
    .sgroup { flex: 0 1 auto; max-width: 100%; }
    .sgroup .sg-head { justify-content: center; }
    .sg-grid { justify-content: center; }
    .tier.t3 .sgroup { max-width: 1450px; }   /* the 72 members wrap into 3 even rows of 24 */
    .seat.empty { border-style: dashed; opacity: .45; }
    .sg-head { display: flex; justify-content: space-between; gap: 14px; font-size: 16px; font-weight: 600; color: var(--muted); margin-bottom: 8px; }
    .sg-head b { color: var(--text); }
    .sg-grid { display: flex; flex-wrap: wrap; gap: 8px; }
    .seat {
        width: 52px; height: 52px; border-radius: 50%; display: grid; place-items: center;
        font-size: 17px; font-weight: 700; color: rgba(255,255,255,.45);
        border: 1.5px solid rgba(255,255,255,.22); background: rgba(255,255,255,.03);
        transition: all .5s cubic-bezier(.2,.8,.2,1);
    }
    .seat.on { color: #2b1500; border-color: transparent; background: radial-gradient(circle at 30% 25%, #ffd08a, var(--orange)); box-shadow: 0 0 14px rgba(247,142,30,.75); transform: scale(1.04); }
    /* photo seats: absent = dim black-and-white, present = full colour inside an orange ring */
    .seat.ph { overflow: hidden; background-size: cover; background-position: center top; background-repeat: no-repeat; color: transparent; }
    .seat.ph:not(.on) { filter: grayscale(1) brightness(.55); border-color: rgba(255,255,255,.18); }
    .seat.ph.on { border: 2px solid var(--orange); background-color: transparent; }
    .av.ph { background-size: cover; background-position: center top; color: transparent; border: 2px solid var(--orange); }
    .seat.pop { animation: pop .9s cubic-bezier(.2,1.6,.3,1); }
    @keyframes pop { 0% { transform: scale(.4); box-shadow: 0 0 0 0 rgba(255,198,110,.9); } 60% { transform: scale(1.5); } 100% { transform: scale(1.04); box-shadow: 0 0 14px rgba(247,142,30,.75); } }

    /* ---------- arrivals ticker ---------- */
    /* the last 10 arrivals as a slim strip: face + name + weight and time */
    .row { display: flex; gap: 12px; align-items: center; padding: 4px 0; border-bottom: 1px solid rgba(255,255,255,.06); }
    .row:last-child { border-bottom: 0; }
    .row .who { flex: 1; min-width: 0; }
    .row .r { display: flex; align-items: center; gap: 6px; margin-top: 3px; }
    .row .role { text-align: left; font-size: 12px; color: var(--muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-top: 2px; }
    .row .t { display: inline; margin: 0; font-size: 12px; }
    .row .w { padding: 1px 8px; font-size: 12px; }
    .av { flex: 0 0 auto; width: 54px; height: 54px; font-size: 20px; border-radius: 50%; display: grid; place-items: center; font-weight: 800; color: #2b1500; background: linear-gradient(145deg, #ffd08a, var(--orange)); }
    .who { min-width: 0; }
    .who .n, .who .r { text-align: left; }
    .who .n { font-size: 17px; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .who .r { font-size: 12px; color: var(--muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .meta { text-align: right; }
    .w { display: inline-block; padding: 3px 10px; border-radius: 99px; font-size: 12px; font-weight: 800; color: var(--gold); background: rgba(247,142,30,.16); border: 1px solid rgba(247,142,30,.35); }
    .t { display: block; margin-top: 4px; font-size: 11px; color: var(--muted); }
    .row.fresh { animation: slide .8s cubic-bezier(.2,.9,.3,1); background: linear-gradient(90deg, rgba(247,142,30,.16), transparent); }
    @keyframes slide { from { opacity: 0; transform: translateY(-14px); } to { opacity: 1; transform: none; } }
    .empty { color: var(--muted); font-size: 14px; padding: 18px 0; text-align: center; }

    /* ---------- quorum-reached banner ---------- */
    .toast {
        position: fixed; left: 50%; top: 22px; z-index: 20; transform: translate(-50%, -260%); opacity: 0;
        padding: 16px 30px; border-radius: 18px; font-size: 20px; font-weight: 800; letter-spacing: .04em;
        background: linear-gradient(135deg, #14b57f, #34e0a0); color: #032b1c;
        box-shadow: 0 20px 60px rgba(52,224,160,.45);
        transition: transform .7s cubic-bezier(.2,1.2,.3,1), opacity .4s;
    }
    .toast.show { transform: translate(-50%, 0); opacity: 1; }

    #stage { transform-origin: 0 0; }

    #confetti { position: fixed; inset: 0; width: 100%; height: 100%; pointer-events: none; z-index: 30; }

    /* read from across the hall */
    .kicker { font-size: 15px; } .sub { font-size: 18px; } .card h2 { font-size: 15px; }
    .kpi .label { font-size: 15px; } .kpi .value { font-size: clamp(34px, 3vw, 54px); } .kpi .hint { font-size: 16px; }
    .big-sub { font-size: 14px; } .big-of { font-size: 20px; } .ms-name { font-size: 15px; } .ms-name b { font-size: 19px; } .ms-state { font-size: 19px; }
    .who .n { font-size: 19px; } .row .role { font-size: 14px; } .row .t { font-size: 15px; color: var(--green); font-weight: 700; }
    .legend { font-size: 14px; } .sg-head { font-size: 18px; } .gh { font-size: 13px; }

    @media (max-width: 1250px) {
        .hero, .kpis, .groups, .seats, .ticker { grid-column: span 12; grid-row: auto; }
        .kpis { grid-template-columns: repeat(2, 1fr); }
    }
    @media (prefers-reduced-motion: reduce) { * { animation: none !important; transition: none !important; } }
</style>
@endverbatim
</head>
<body>
{{-- The voting TV screen: who has voted, live. It never shows what anybody voted, and no member ids or phones. --}}
<div id="stage">
<header>
    <div class="brand">
        <div class="mark">
            <img src="{{ url('council-board/fpm-logo.png') }}" alt="">
        </div>
        <div style="min-width:0">
            <div class="kicker">National Council &middot; Live voting</div>
            <h1 class="auto" id="title">{{ $poll->title }}</h1>
            <div class="sub" id="subtitle"></div>
        </div>
    </div>
    <div class="tools">
        <div class="live" id="live"><i></i><span id="liveText">LIVE</span></div>
        <div id="clock" class="num">--:--:--</div>
        <button class="btn" id="fs" type="button">Full screen</button>
        <a class="btn" href="{{ $backUrl }}">Back</a>
    </div>
</header>

<main>
    <section class="card hero">
        <h2 style="align-self:stretch">Turnout <span id="pctTop" class="num"></span></h2>
        <div class="gauge">
            <svg viewBox="0 0 520 520" aria-hidden="true">
                <defs>
                    <linearGradient id="g1" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#f48d24"/><stop offset="1" stop-color="#f48d24"/></linearGradient>
                    <linearGradient id="g2" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#34e0a0"/><stop offset="1" stop-color="#19b57f"/></linearGradient>
                </defs>
                <g transform="rotate(-90 260 260)">
                    <circle class="track" cx="260" cy="260" r="212"/>
                    <circle class="arc glow" id="arcGlow" cx="260" cy="260" r="212" stroke="url(#g1)"/>
                    <circle class="arc" id="arc" cx="260" cy="260" r="212" stroke="url(#g1)"/>
                </g>
                <g id="ticks"></g>
            </svg>
            <div class="gauge-center">
                <div class="big num" id="bigNum">0</div>
                <div class="big-sub">have voted</div>
                <div class="big-of">of <b class="num" id="bigTotal">0</b></div>
            </div>
        </div>
        <div class="milestones">
            <div class="ms" id="msMaj">
                <div class="ms-top"><span class="ms-name">Voters <b class="num" id="majNum">0</b></span><span class="ms-state" id="majState"></span></div>
                <div class="ms-bar"><i id="majBar"></i></div>
            </div>
            <div class="ms" id="msTwo">
                <div class="ms-top"><span class="ms-name">Weight <b class="num" id="twoNum">0</b></span><span class="ms-state" id="twoState"></span></div>
                <div class="ms-bar"><i id="twoBar"></i></div>
            </div>
        </div>
    </section>

    <section class="kpis">
        <div class="kpi" style="--accent: var(--green)"><div class="label">Voted</div><div class="value num"><span id="kPresent">0</span><small>/ <span id="kMembers">0</span></small></div><div class="hint">voters</div></div>
        <div class="kpi" style="--accent: var(--red)"><div class="label">Still to vote</div><div class="value num"><span id="kAbsent">0</span></div><div class="hint">not voted yet</div></div>
        <div class="kpi" style="--accent: var(--blue)"><div class="label">Weight turnout</div><div class="value num"><span id="kPct">0</span><small>%</small></div><div class="hint">of total weight</div></div>
        <div class="kpi" style="--accent: var(--orange)"><div class="label" id="kNextLabel">Closes in</div><div class="value num" id="kNext">-</div><div class="hint" id="kNextHint"></div></div>
    </section>

    <section class="card groups">
        <h2>Voting by role<span id="grpCount" class="num"></span></h2>
        <div class="grp grp-head"><div class="gh">Members</div><div></div><div></div><div class="gh gh-w">Weight</div></div>
        <div id="groupBars"></div>
    </section>

    <section class="card seats">
        <h2>Seat map <span class="legend"><span><i class="on"></i>VOTED</span><span><i class="off"></i>NOT YET</span></span></h2>
        <div id="seatMap"></div>
    </section>

    <section class="card ticker">
        <h2>Latest voters</h2>
        <div id="recent"></div>
    </section>
</main>
</div>

<canvas id="confetti"></canvas>
<div class="toast" id="toast"></div>

<script>
    window.BOARD = {!! json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!};
    window.BOARD_URL = {!! json_encode($dataUrl) !!};
</script>
@verbatim
<script>
(function () {
    var R = 212, C = 2 * Math.PI * R, CX = 260, CY = 260;
    var $ = function (id) { return document.getElementById(id); };
    var state = { first: true, seen: {}, presentIds: {}, reached: { maj: false, two: false }, shown: { weight: 0, present: 0, absent: 0, pct: 0 } };

    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
    function fmt(n) { return Number(n).toLocaleString('en-US'); }

    // numbers glide to their new value instead of jumping
    function countTo(el, key, to) {
        var from = state.shown[key] || 0;
        state.shown[key] = to;
        if (from === to) { el.textContent = fmt(to); return; }
        var t0 = performance.now(), dur = 1100;
        clearTimeout(el._final);
        el._final = setTimeout(function () { el.textContent = fmt(to); }, dur + 80);
        (function step(now) {
            var p = Math.min(1, (now - t0) / dur), e = 1 - Math.pow(1 - p, 3);
            el.textContent = fmt(Math.round(from + (to - from) * e));
            if (p < 1) requestAnimationFrame(step);
        })(t0);
    }

    function drawGauge(s) {
        var frac = s.member_count ? Math.min(1, s.present_count / s.member_count) : 0;
        var done = s.member_count > 0 && s.present_count >= s.member_count;
        [$('arc'), $('arcGlow')].forEach(function (el) {
            el.setAttribute('stroke-dasharray', C);
            el.setAttribute('stroke-dashoffset', C * (1 - frac));
            el.setAttribute('stroke', done ? 'url(#g2)' : 'url(#g1)');
        });
    }

    function drawMilestone(prefix, part, total) {
        var p = total ? Math.round(part * 100 / total) : 0;
        $(prefix + 'Num').textContent = fmt(part) + ' / ' + fmt(total);
        $(prefix + 'State').textContent = p + '%';
        $(prefix + 'Bar').style.width = Math.min(100, p) + '%';
        $(prefix === 'maj' ? 'msMaj' : 'msTwo').classList.toggle('reached', total > 0 && part >= total);
    }

    function drawStats(d) {
        var s = d.stats;
        var pct = s.member_count ? Math.round(s.present_count * 100 / s.member_count) : 0;
        countTo($('bigNum'), 'weight', s.present_count);
        $('bigTotal').textContent = fmt(s.member_count);
        $('pctTop').textContent = pct + '%';
        countTo($('kPresent'), 'present', s.present_count);
        $('kMembers').textContent = fmt(s.member_count);
        countTo($('kAbsent'), 'absent', s.absent_count);
        countTo($('kPct'), 'pct', s.total_weight ? Math.round(s.present_weight * 100 / s.total_weight) : 0);
        drawGauge(s);
        drawMilestone('maj', s.present_count, s.member_count);
        drawMilestone('two', s.present_weight, s.total_weight);

        closing = { left: d.meeting.seconds_left, at: d.meeting.closes_at, read: Date.now(), open: d.meeting.is_active };
        countdown();
        var live = $('live');
        live.className = 'live' + (d.meeting.is_active ? '' : ' closed');
        $('liveText').textContent = d.meeting.is_active ? 'LIVE' : d.meeting.state;
        $('title').textContent = d.meeting.title;
        $('subtitle').textContent = fmt(s.member_count) + ' voters · weight ' + fmt(s.total_weight);
    }

    // counted from the server's "seconds left", so a TV with a wrong clock still shows the right time
    var closing = null;
    function countdown() {
        if (!closing) { return; }
        var left = closing.left === null ? null : Math.max(0, closing.left - Math.round((Date.now() - closing.read) / 1000));
        var two = function (n) { return ('0' + n).slice(-2); };
        if (!closing.open) { $('kNextLabel').textContent = 'Voting'; $('kNext').textContent = 'Closed'; $('kNextHint').textContent = closing.at ? 'closing time ' + closing.at : ''; return; }
        if (left === null) { $('kNextLabel').textContent = 'Voting'; $('kNext').textContent = 'Open'; $('kNextHint').textContent = 'no closing time'; return; }
        var h = Math.floor(left / 3600), m = Math.floor(left % 3600 / 60), sec = left % 60;
        $('kNextLabel').textContent = 'Closes in';
        $('kNext').textContent = (h ? h + ':' : '') + two(m) + ':' + two(sec);
        $('kNextHint').textContent = 'at ' + closing.at;
    }
    setInterval(countdown, 1000);


    // ---- the council tree: the level (row) every role belongs to ----
    function norm(s) { return String(s || '').replace(/[أإآ]/g, 'ا').replace(/\s+/g, ' ').trim(); }
    // offices that always get a place beside the vice presidents, even while nobody holds them
    var VP_OFFICES = ['مكتب طلاب الجامعات والمعاهد العليا', 'مكتب طلاب المهنيات', 'مكتب تلامذة المدارس', 'مكتب شباب الاقضية'];
    function officeIndex(name) {
        var n = norm(name);
        for (var i = 0; i < VP_OFFICES.length; i++) { if (norm(VP_OFFICES[i]) === n) { return i; } }
        return -1;
    }
    // 0 president | 1 نائب | 2 vice presidents | 3 عضو | 4 انتشار | 5 sector representatives | 6 offices
    function tierOf(name) {
        if (name === 'الرئيس') { return 0; }
        if (name === 'نائب') { return 1; }
        if (name.indexOf('نائب') === 0) { return 2; }
        if (name === 'عضو') { return 3; }
        if (name.indexOf('انتشار') === 0) { return 4; }
        var n = norm(name);
        if (officeIndex(name) > -1 || n.indexOf('مكتب') === 0 || n.indexOf('ارباب') === 0) { return 6; }
        return 5;
    }

    function drawGroups(d) {
        // one bar per level of the tree: all the انتشار regions are a single bar, all the other offices another
        var levels = [
            { name: 'الرئيس', weight: 0, weight_present: 0 },
            { name: 'نائب', weight: 0, weight_present: 0 },
            { name: 'نائب الرئيس', weight: 0, weight_present: 0 },
            { name: 'مجلس قضاء', weight: 0, weight_present: 0 },   // the عضو role is shown as "مجلس قضاء"
            { name: 'انتشار', weight: 0, weight_present: 0 },
            { name: 'القطاعات والمكاتب', weight: 0, weight_present: 0 }
        ];
        d.groups.forEach(function (g) {
            var t = Math.min(tierOf(g.name), 5);   // sector representatives and offices share the last bar
            levels[t].weight += g.weight; levels[t].weight_present += g.weight_present;
            levels[t].members = (levels[t].members || 0) + g.members; levels[t].present = (levels[t].present || 0) + g.present;
        });
        levels = levels.filter(function (l) { return l.weight > 0; });
        $('grpCount').textContent = levels.length + ' levels';
        $('groupBars').innerHTML = levels.map(function (g) {
            var p = g.weight ? Math.round(g.weight_present * 100 / g.weight) : 0;
            return '<div class="grp"><div class="grp-count num"><b>' + g.present + '</b> / ' + g.members + '</div>' +
                '<div class="grp-name auto" title="' + esc(g.name) + '">' + esc(g.name) + '</div>' +
                '<div class="grp-bar"><i class="' + (p >= 100 ? 'full' : '') + '" style="width:' + p + '%"></i></div>' +
                '<div class="grp-num num"><b>' + g.weight_present + '</b> / ' + g.weight + '</div></div>';
        }).join('') || '<div class="empty">No members yet</div>';
    }

    function drawSeats(d) {
        var map = $('seatMap');
        // rebuilt whenever the council itself changes (e.g. the women's representative is added to the roster mid-meeting)
        var sig = d.members.map(function (m) { return m.id + '|' + m.sector; }).join(',');
        var rebuilt = false;
        if (map.getAttribute('data-built') !== sig) {
            rebuilt = !!map.getAttribute('data-built');
            if (rebuilt) { state.presentIds = {}; }
            var byGroup = {};
            d.members.forEach(function (m) { (byGroup[m.sector] = byGroup[m.sector] || []).push(m); });
            var order = d.groups.map(function (g) { return g.name; });
            // the council as a tree: president, نائب, vice presidents + offices, members, the diaspora, then the rest
            var tiers = [[], [], [], [], [], [], []];
            order.forEach(function (name) { tiers[tierOf(name)].push(name); });
            // row 5 = sector representatives, row 6 (the last) = the offices: the other offices first, then the four fixed
            // offices at the very end (a placeholder when nobody holds one)
            var others = tiers[6].filter(function (n) { return officeIndex(n) < 0; });
            var offices = VP_OFFICES.map(function (canon) {
                var found = tiers[6].filter(function (n) { return norm(n) === norm(canon); })[0];
                return found;
            }).filter(Boolean);
            tiers[6] = others.concat(offices);
            map.innerHTML = tiers.map(function (names, t) {
                if (!names.length) { return ''; }
                return '<div class="tier t' + t + '">' + names.map(function (name) {
                    var list = byGroup[name] || [];
                    return '<div class="sgroup" data-g="' + esc(name) + '"><div class="sg-head"><span class="auto">' + esc(name === 'عضو' ? 'مجلس قضاء' : name) + '</span><span class="num"><b data-c="' + esc(name) + '">0</b> / ' + list.length + '</span></div><div class="sg-grid">' +
                        (list.length ? list.map(function (m) { return '<i class="seat" id="s' + esc(m.id) + '"></i>'; }).join('') : '<i class="seat empty"></i>') + '</div></div>';
                }).join('') + '</div>';
            }).join('');
            map.setAttribute('data-built', sig);
        }
        d.members.forEach(function (m) {
            var el = document.getElementById('s' + m.id);
            if (!el) { return; }
            el.title = m.name + (m.role ? ' — ' + m.role : '') + (m.at ? ' · ' + m.at : '');
            if (m.photo) {
                // the picture replaces the letter; if it cannot be loaded the letter comes back
                if (!el.getAttribute('data-ph')) {
                    el.setAttribute('data-ph', '1');
                    var probe = new Image();
                    probe.onload = function () { el.style.backgroundImage = 'url("' + m.photo + '")'; el.classList.add('ph'); el.textContent = ''; };
                    probe.onerror = function () { el.textContent = (m.name || '?').trim().charAt(0); };
                    probe.src = m.photo;
                }
            } else {
                el.textContent = (m.name || '?').trim().charAt(0);
            }
            var was = !!state.presentIds[m.id];
            if (m.present && !was) {
                el.classList.add('on');
                if (!state.first && !rebuilt) { el.classList.remove('pop'); void el.offsetWidth; el.classList.add('pop'); }
            }
            if (!m.present) { el.classList.remove('on', 'pop'); }
            state.presentIds[m.id] = m.present;
        });
        d.groups.forEach(function (g) {
            var c = map.querySelector('[data-c="' + g.name.replace(/"/g, '\\"') + '"]');
            if (c) { c.textContent = g.present; }
        });
    }

    function drawRecent(d) {
        if (!d.recent.length) { $('recent').innerHTML = '<div class="empty">Waiting for the first vote&hellip;</div>'; return; }
        $('recent').innerHTML = d.recent.slice(0, 10).map(function (r) {
            var fresh = !state.first && !state.seen[r.id];
            return '<div class="row' + (fresh ? ' fresh' : '') + '"><div class="av' + (r.photo ? ' ph' : '') + '"' + (r.photo ? ' style="background-image:url(&quot;' + esc(r.photo) + '&quot;)"' : '') + '>' + (r.photo ? '' : esc((r.name || '?').trim().charAt(0))) + '</div>' +
                '<div class="who"><div class="n auto">' + esc(r.name) + '</div><div class="role auto">' + esc(r.role === 'عضو' ? 'مجلس قضاء' : r.role) + '</div>' +
                '<div class="r"><span class="t num">&#10004; ' + esc(r.at) + '</span></div></div></div>';
        }).join('');
        d.recent.forEach(function (r) { state.seen[r.id] = true; });
    }

    // a short burst of confetti in the party colours
    function confetti() {
        var cv = $('confetti'), ctx = cv.getContext('2d');
        cv.width = window.innerWidth; cv.height = window.innerHeight;
        var colors = ['#f78e1e', '#ffc66e', '#34e0a0', '#1f86bd', '#f7f5f0'];
        var bits = [];
        for (var i = 0; i < 160; i++) {
            bits.push({ x: cv.width / 2 + (Math.random() - .5) * 240, y: cv.height * .18, vx: (Math.random() - .5) * 16, vy: Math.random() * -13 - 3,
                        g: .32 + Math.random() * .2, s: 5 + Math.random() * 7, r: Math.random() * 6, vr: (Math.random() - .5) * .4, c: colors[i % colors.length] });
        }
        var t0 = performance.now();
        (function frame(now) {
            ctx.clearRect(0, 0, cv.width, cv.height);
            bits.forEach(function (b) {
                b.vy += b.g; b.x += b.vx; b.y += b.vy; b.r += b.vr; b.vx *= .995;
                ctx.save(); ctx.translate(b.x, b.y); ctx.rotate(b.r); ctx.fillStyle = b.c; ctx.fillRect(-b.s / 2, -b.s / 3, b.s, b.s * .6); ctx.restore();
            });
            if (now - t0 < 4200) { requestAnimationFrame(frame); } else { ctx.clearRect(0, 0, cv.width, cv.height); }
        })(t0);
    }

    // on a projector the board must fit the screen without scrolling: shrink it to fit when it is taller
    // Scale the whole board so it fills the screen exactly (down on a small screen, up a little on a big one).
    // The scaled page is laid out at a different width, which changes its height, so measure again after
    // every step until the scale settles.
    function fit() {
        var st = $('stage');
        if (window.innerWidth < 1250) { st.style.transform = ''; st.style.width = ''; document.body.style.overflow = ''; return; }
        document.body.style.overflow = 'hidden';
        var have = window.innerHeight, z = 1;
        for (var i = 0; i < 8; i++) {
            st.style.width = (100 / z) + '%';
            st.style.transform = 'scale(' + z + ')';
            var nz = Math.min(3, Math.max(.5, have / st.offsetHeight));
            var done = Math.abs(nz - z) < .004;
            z = nz;
            if (done) { break; }
        }
        // safety: never end up taller than the screen (nudge the scale down until it fits)
        for (var j = 0; j < 12; j++) {
            st.style.width = (100 / z) + '%';
            st.style.transform = 'scale(' + z + ')';
            if (st.offsetHeight * z <= have - 2) { break; }
            z *= 0.985;
        }
    }

    function celebrate(text) {
        confetti();
        var t = $('toast');
        t.textContent = text;
        t.classList.add('show');
        setTimeout(function () { t.classList.remove('show'); }, 5200);
    }

    function render(d) {
        drawStats(d);
        drawGroups(d);
        drawSeats(d);
        drawRecent(d);
        var s = d.stats;
        var everyone = s.member_count > 0 && s.present_count >= s.member_count;
        if (!state.first && everyone && !state.everyone) { celebrate('✔  Everyone has voted'); }
        state.everyone = everyone;
        state.first = false;
        setTimeout(fit, 50);
    }

    // ---- clock, full screen, live refresh ----
    function clock() { var n = new Date(); $('clock').textContent = n.toLocaleTimeString('en-GB'); }
    clock(); setInterval(clock, 1000);

    $('fs').addEventListener('click', function () {
        if (!document.fullscreenElement) { document.documentElement.requestFullscreen && document.documentElement.requestFullscreen(); }
        else { document.exitFullscreen(); }
    });
    document.addEventListener('keydown', function (e) { if (e.key === 'f' || e.key === 'F') { $('fs').click(); } });
    document.addEventListener('fullscreenchange', function () {
        $('fs').textContent = document.fullscreenElement ? 'Exit full screen' : 'Full screen';
        setTimeout(fit, 120);   // the window size settles a moment after the switch
    });

    render(window.BOARD);
    setTimeout(fit, 150);
    window.addEventListener('resize', fit);

    function refresh() {
        fetch(window.BOARD_URL, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(function (r) { if (!r.ok) { throw new Error(r.status); } return r.json(); })
            .then(function (d) { render(d); })
            .catch(function () { var l = $('live'); l.className = 'live lost'; $('liveText').textContent = 'RECONNECTING'; });
    }
    setInterval(refresh, 4000);
})();
</script>
@endverbatim
</body>
</html>
