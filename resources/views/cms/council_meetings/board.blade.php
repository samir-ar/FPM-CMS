<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Live attendance - {{ $meeting->title }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
@verbatim
<style>
    :root {
        --bg1: #040c1a;
        --bg2: #0b2748;
        --card: rgba(255, 255, 255, .055);
        --card-strong: rgba(255, 255, 255, .09);
        --line: rgba(255, 255, 255, .11);
        --text: #eef4fb;
        --muted: #8ea6c3;
        --orange: #f78e1e;
        --gold: #ffc66e;
        --blue: #2aa5e0;
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
            radial-gradient(1200px 700px at 12% -10%, rgba(247, 142, 30, .16), transparent 60%),
            radial-gradient(1000px 800px at 105% 10%, rgba(42, 165, 224, .18), transparent 55%),
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
    .mark {
        flex: none; width: 58px; height: 58px; border-radius: 50%;
        background: linear-gradient(145deg, #ffa83d, #f77f00);
        display: grid; place-items: center;
        box-shadow: 0 10px 30px rgba(247, 142, 30, .45), inset 0 2px 0 rgba(255,255,255,.35);
    }
    .mark svg { width: 34px; height: 34px; }
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
        grid-template-rows: auto 1fr auto;
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
    .seats { grid-column: span 8; }
    .ticker { grid-column: span 4; }

    /* ---------- the gauge ---------- */
    .gauge { position: relative; width: min(100%, 430px); aspect-ratio: 1; margin: 2px auto 6px; }
    .gauge svg { width: 100%; height: 100%; overflow: visible; }
    .track { fill: none; stroke: rgba(255,255,255,.08); stroke-width: 26; }
    .arc { fill: none; stroke-width: 26; stroke-linecap: round; transition: stroke-dashoffset 1.4s cubic-bezier(.2,.8,.2,1); }
    .arc.glow { filter: blur(11px); opacity: .55; }
    .tick { stroke: rgba(255,255,255,.55); stroke-width: 3; stroke-linecap: round; transition: stroke .5s; }
    .tick.hit { stroke: var(--green); }
    .tick-label { fill: #c9d8ea; font: 800 17px 'Inter', sans-serif; letter-spacing: .06em; text-anchor: middle; transition: fill .5s; }
    .tick-label.hit { fill: var(--green); }
    .gauge-center { position: absolute; inset: 0; display: grid; place-content: center; text-align: center; }
    .big { font-size: clamp(60px, 6.2vw, 100px); font-weight: 800; line-height: 1; letter-spacing: -.03em; background: linear-gradient(180deg, #fff, #ffd9a1); -webkit-background-clip: text; background-clip: text; color: transparent; }
    .big-sub { margin-top: 8px; font-size: 14px; letter-spacing: .22em; text-transform: uppercase; color: var(--muted); font-weight: 600; }
    .big-of { margin-top: 12px; font-size: 22px; font-weight: 600; color: var(--text); }
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
    .ms-bar i { display: block; height: 100%; width: 0; border-radius: 99px; background: linear-gradient(90deg, var(--orange), var(--gold)); transition: width 1.2s cubic-bezier(.2,.8,.2,1); }
    .ms.reached { background: rgba(52, 224, 160, .09); border-color: rgba(52, 224, 160, .45); }
    .ms.reached .ms-state { color: var(--green); }
    .ms.reached .ms-bar i { background: linear-gradient(90deg, #19c58a, var(--green)); }

    /* ---------- KPI cards ---------- */
    .kpi { position: relative; overflow: hidden; padding: clamp(14px, 1.2vw, 20px); border-radius: var(--radius); background: var(--card); border: 1px solid var(--line); box-shadow: 0 18px 40px rgba(0,0,0,.25), inset 0 1px 0 rgba(255,255,255,.06); }
    .kpi::after { content: ''; position: absolute; right: -30px; top: -30px; width: 110px; height: 110px; border-radius: 50%; background: var(--accent, var(--orange)); opacity: .16; filter: blur(8px); }
    .kpi .label { font-size: 11px; font-weight: 700; letter-spacing: .2em; text-transform: uppercase; color: var(--muted); }
    .kpi .value { margin-top: 8px; font-size: clamp(30px, 3vw, 50px); font-weight: 800; line-height: 1; }
    .kpi .value small { font-size: .42em; font-weight: 600; color: var(--muted); margin-left: 4px; }
    .kpi .hint { margin-top: 9px; font-size: 13px; color: var(--muted); }

    /* ---------- bars by sector ---------- */
    .grp { display: grid; grid-template-columns: minmax(110px, 190px) 1fr auto; gap: 14px; align-items: center; padding: 10px 0; }
    .grp-name { font-size: 14px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .grp-bar { height: 14px; border-radius: 99px; background: rgba(255,255,255,.08); overflow: hidden; }
    .grp-bar i { display: block; height: 100%; width: 0; border-radius: 99px; background: linear-gradient(90deg, var(--blue), #6fd0ff); transition: width 1.2s cubic-bezier(.2,.8,.2,1); }
    .grp-bar i.full { background: linear-gradient(90deg, #19c58a, var(--green)); }
    .grp-num { font-size: 13px; color: var(--muted); min-width: 86px; text-align: right; }
    .grp-num b { color: var(--text); font-weight: 700; }

    /* ---------- seat map ---------- */
    .legend { display: flex; gap: 16px; font-size: 11px; letter-spacing: .12em; }
    .legend span { display: inline-flex; align-items: center; gap: 7px; }
    .legend i { width: 12px; height: 12px; border-radius: 50%; }
    .legend .on { background: var(--orange); box-shadow: 0 0 10px rgba(247,142,30,.8); }
    .legend .off { border: 1.5px solid rgba(255,255,255,.3); }
    /* the seat map is a tree: one centred row per level, joined by a thin line */
    #seatMap { display: flex; flex-direction: column; align-items: stretch; gap: 22px; }
    .tier { position: relative; display: flex; flex-wrap: wrap; justify-content: center; gap: 14px 40px; }
    .tier + .tier::before { content: ''; position: absolute; top: -22px; left: 50%; height: 22px; width: 1px; background: linear-gradient(rgba(247,142,30,.0), rgba(247,142,30,.55)); }
    .tier.t0 .seat { width: 44px; height: 44px; }
    .sgroup { flex: 0 1 auto; max-width: 100%; }
    .sgroup .sg-head { justify-content: center; }
    .sg-grid { justify-content: center; }
    .tier.t2 .sgroup { max-width: 1100px; }
    .sg-head { display: flex; justify-content: space-between; gap: 14px; font-size: 12px; font-weight: 600; color: var(--muted); margin-bottom: 8px; }
    .sg-head b { color: var(--text); }
    .sg-grid { display: flex; flex-wrap: wrap; gap: 6px; }
    .seat {
        width: 32px; height: 32px; border-radius: 50%; display: grid; place-items: center;
        font-size: 11px; font-weight: 700; color: rgba(255,255,255,.45);
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
    .row { display: grid; grid-template-columns: 40px 1fr auto; gap: 12px; align-items: center; padding: 8px 0; border-bottom: 1px solid rgba(255,255,255,.06); }
    .row:last-child { border-bottom: 0; }
    .av { width: 40px; height: 40px; border-radius: 50%; display: grid; place-items: center; font-weight: 800; color: #2b1500; background: linear-gradient(145deg, #ffd08a, var(--orange)); }
    .who { min-width: 0; }
    .who .n, .who .r { text-align: left; }
    .who .n { font-size: 15px; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
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

    #confetti { position: fixed; inset: 0; width: 100%; height: 100%; pointer-events: none; z-index: 30; }

    @media (max-width: 1250px) {
        .hero, .kpis, .groups, .seats, .ticker { grid-column: span 12; grid-row: auto; }
        .kpis { grid-template-columns: repeat(2, 1fr); }
    }
    @media (prefers-reduced-motion: reduce) { * { animation: none !important; transition: none !important; } }
</style>
@endverbatim
</head>
<body>

<header>
    <div class="brand">
        <div class="mark">
            <svg viewBox="0 0 64 64" aria-hidden="true"><path d="M8 33c9-1 17 3 22 12C38 28 49 14 62 6c-8 12-15 25-20 52C37 47 24 38 8 33z" fill="#fff"/></svg>
        </div>
        <div style="min-width:0">
            <div class="kicker">National Council &middot; Live attendance</div>
            <h1 class="auto" id="title">{{ $meeting->title }}</h1>
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
        <h2 style="align-self:stretch">Quorum <span id="pctTop" class="num"></span></h2>
        <div class="gauge">
            <svg viewBox="0 0 520 520" aria-hidden="true">
                <defs>
                    <linearGradient id="g1" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#ffb457"/><stop offset="1" stop-color="#f77f00"/></linearGradient>
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
                <div class="big-sub">weight present</div>
                <div class="big-of">of <b class="num" id="bigTotal">0</b></div>
            </div>
        </div>
        <div class="milestones">
            <div class="ms" id="msMaj">
                <div class="ms-top"><span class="ms-name">Half + 1 <b class="num" id="majNum">0</b></span><span class="ms-state" id="majState"></span></div>
                <div class="ms-bar"><i id="majBar"></i></div>
            </div>
            <div class="ms" id="msTwo">
                <div class="ms-top"><span class="ms-name">Two-thirds <b class="num" id="twoNum">0</b></span><span class="ms-state" id="twoState"></span></div>
                <div class="ms-bar"><i id="twoBar"></i></div>
            </div>
        </div>
    </section>

    <section class="kpis">
        <div class="kpi" style="--accent: var(--orange)"><div class="label">Present</div><div class="value num"><span id="kPresent">0</span><small>/ <span id="kMembers">0</span></small></div><div class="hint">council members</div></div>
        <div class="kpi" style="--accent: var(--red)"><div class="label">Absent</div><div class="value num"><span id="kAbsent">0</span></div><div class="hint">still to arrive</div></div>
        <div class="kpi" style="--accent: var(--blue)"><div class="label">Turnout</div><div class="value num"><span id="kPct">0</span><small>%</small></div><div class="hint">of total weight</div></div>
        <div class="kpi" style="--accent: var(--green)"><div class="label">Next milestone</div><div class="value num" id="kNext">-</div><div class="hint" id="kNextHint">weight to go</div></div>
    </section>

    <section class="card groups">
        <h2>Attendance by role<span id="grpCount" class="num"></span></h2>
        <div id="groupBars"></div>
    </section>

    <section class="card seats">
        <h2>Seat map <span class="legend"><span><i class="on"></i>PRESENT</span><span><i class="off"></i>ABSENT</span></span></h2>
        <div id="seatMap"></div>
    </section>

    <section class="card ticker">
        <h2>Latest arrivals</h2>
        <div id="recent"></div>
    </section>
</main>

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

    function tickAt(frac, cls) {
        var a = frac * 2 * Math.PI - Math.PI / 2;
        var x1 = CX + (R - 22) * Math.cos(a), y1 = CY + (R - 22) * Math.sin(a);
        var x2 = CX + (R + 24) * Math.cos(a), y2 = CY + (R + 24) * Math.sin(a);
        var lx = CX + (R + 46) * Math.cos(a), ly = CY + (R + 46) * Math.sin(a) + 5;
        return { line: '<line class="tick ' + cls + '" x1="' + x1 + '" y1="' + y1 + '" x2="' + x2 + '" y2="' + y2 + '"/>', lx: lx, ly: ly };
    }

    function drawGauge(s) {
        var total = s.total_weight || 1;
        var frac = Math.min(1, s.present_weight / total);
        var off = C * (1 - frac);
        [$('arc'), $('arcGlow')].forEach(function (el) {
            el.setAttribute('stroke-dasharray', C);
            el.setAttribute('stroke-dashoffset', off);
            el.setAttribute('stroke', s.majority_reached ? 'url(#g2)' : 'url(#g1)');
        });
        var m = tickAt(s.quorum_majority / total, s.majority_reached ? 'hit' : '');
        var t = tickAt(s.quorum_two_thirds / total, s.two_thirds_reached ? 'hit' : '');
        $('ticks').innerHTML = m.line + t.line +
            '<text class="tick-label ' + (s.majority_reached ? 'hit' : '') + '" x="' + m.lx + '" y="' + m.ly + '">&#189; + 1</text>' +
            '<text class="tick-label ' + (s.two_thirds_reached ? 'hit' : '') + '" x="' + t.lx + '" y="' + t.ly + '">2/3</text>';
    }

    function drawMilestone(prefix, needed, target, present, reached) {
        $(prefix + 'Num').textContent = fmt(target);
        $(prefix + 'State').textContent = reached ? '✔ Quorum reached' : fmt(needed) + ' to go';
        $(prefix + 'Bar').style.width = Math.min(100, target ? (present / target) * 100 : 0) + '%';
        $(prefix === 'maj' ? 'msMaj' : 'msTwo').classList.toggle('reached', !!reached);
    }

    function drawStats(d) {
        var s = d.stats;
        var pct = s.total_weight ? Math.round(s.present_weight * 100 / s.total_weight) : 0;
        countTo($('bigNum'), 'weight', s.present_weight);
        $('bigTotal').textContent = fmt(s.total_weight);
        $('pctTop').textContent = pct + '% present';
        countTo($('kPresent'), 'present', s.present_count);
        $('kMembers').textContent = fmt(s.member_count);
        countTo($('kAbsent'), 'absent', s.absent_count);
        countTo($('kPct'), 'pct', pct);
        drawGauge(s);
        drawMilestone('maj', s.needed_majority, s.quorum_majority, s.present_weight, s.majority_reached);
        drawMilestone('two', s.needed_two_thirds, s.quorum_two_thirds, s.present_weight, s.two_thirds_reached);

        // the next target to aim for
        if (!s.majority_reached) { $('kNext').textContent = fmt(s.needed_majority); $('kNextHint').textContent = 'weight to reach half + 1'; }
        else if (!s.two_thirds_reached) { $('kNext').textContent = fmt(s.needed_two_thirds); $('kNextHint').textContent = 'weight to reach two-thirds'; }
        else { $('kNext').textContent = '✔'; $('kNextHint').textContent = 'both quorums reached'; }

        var live = $('live');
        live.className = 'live' + (d.meeting.is_active ? '' : ' closed');
        $('liveText').textContent = d.meeting.is_active ? 'LIVE' : 'CLOSED';
        $('title').textContent = d.meeting.title;
        $('subtitle').textContent = [d.meeting.date, fmt(s.member_count) + ' members · weight ' + fmt(s.total_weight)].filter(Boolean).join('  ·  ');
    }

    function drawGroups(d) {
        $('grpCount').textContent = d.groups.length + ' groups';
        $('groupBars').innerHTML = d.groups.slice(0, 10).map(function (g) {
            var p = g.weight ? Math.round(g.weight_present * 100 / g.weight) : 0;
            return '<div class="grp"><div class="grp-name auto" title="' + esc(g.name) + '">' + esc(g.name) + '</div>' +
                '<div class="grp-bar"><i class="' + (p >= 100 ? 'full' : '') + '" style="width:' + p + '%"></i></div>' +
                '<div class="grp-num num"><b>' + g.weight_present + '</b> / ' + g.weight + '</div></div>';
        }).join('') || '<div class="empty">No members yet</div>';
    }

    function drawSeats(d) {
        var map = $('seatMap');
        if (!map.getAttribute('data-built')) {
            var byGroup = {};
            d.members.forEach(function (m) { (byGroup[m.sector] = byGroup[m.sector] || []).push(m); });
            var order = d.groups.map(function (g) { return g.name; });
            // the council as a tree: president, vice presidents, members, the diaspora, then the offices
            function tierOf(name) {
                if (name === 'الرئيس') { return 0; }          // الرئيس
                if (name.indexOf('نائب') === 0) { return 1; }          // نائب...
                if (name === 'عضو') { return 2; }                           // عضو
                if (name.indexOf('انتشار') === 0) { return 3; } // انتشار...
                return 4;
            }
            var tiers = [[], [], [], [], []];
            order.forEach(function (name) { tiers[tierOf(name)].push(name); });
            map.innerHTML = tiers.map(function (names, t) {
                if (!names.length) { return ''; }
                return '<div class="tier t' + t + '">' + names.map(function (name) {
                    var list = byGroup[name] || [];
                    return '<div class="sgroup" data-g="' + esc(name) + '"><div class="sg-head"><span class="auto">' + esc(name) + '</span><span class="num"><b data-c="' + esc(name) + '">0</b> / ' + list.length + '</span></div><div class="sg-grid">' +
                        list.map(function (m) { return '<i class="seat" id="s' + esc(m.id) + '"></i>'; }).join('') + '</div></div>';
                }).join('') + '</div>';
            }).join('');
            map.setAttribute('data-built', '1');
        }
        d.members.forEach(function (m) {
            var el = document.getElementById('s' + m.id);
            if (!el) { return; }
            el.title = m.name + (m.role ? ' — ' + m.role : '') + ' (weight ' + m.weight + ')' + (m.at ? ' · ' + m.at : '');
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
                if (!state.first) { el.classList.remove('pop'); void el.offsetWidth; el.classList.add('pop'); }
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
        if (!d.recent.length) { $('recent').innerHTML = '<div class="empty">Waiting for the first arrival&hellip;</div>'; return; }
        $('recent').innerHTML = d.recent.slice(0, 6).map(function (r) {
            var fresh = !state.first && !state.seen[r.id];
            return '<div class="row' + (fresh ? ' fresh' : '') + '"><div class="av' + (r.photo ? ' ph' : '') + '"' + (r.photo ? ' style="background-image:url(&quot;' + esc(r.photo) + '&quot;)"' : '') + '>' + (r.photo ? '' : esc((r.name || '?').trim().charAt(0))) + '</div>' +
                '<div class="who"><div class="n auto">' + esc(r.name) + '</div><div class="r auto">' + esc(r.role) + '</div></div>' +
                '<div class="meta"><span class="w num">+' + r.weight + '</span><span class="t num">' + esc(r.at) + '</span></div></div>';
        }).join('');
        d.recent.forEach(function (r) { state.seen[r.id] = true; });
    }

    // a short burst of confetti in the party colours
    function confetti() {
        var cv = $('confetti'), ctx = cv.getContext('2d');
        cv.width = window.innerWidth; cv.height = window.innerHeight;
        var colors = ['#f78e1e', '#ffc66e', '#34e0a0', '#2aa5e0', '#ffffff'];
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
    function fit() {
        document.body.style.zoom = 1;
        if (window.innerWidth < 1250) { return; }
        var need = document.documentElement.scrollHeight, have = window.innerHeight;
        if (need > have) { document.body.style.zoom = Math.max(.55, have / need); }
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
        if (!state.first) {
            if (s.majority_reached && !state.reached.maj) { celebrate('✔  Quorum reached — half + 1'); }
            else if (s.two_thirds_reached && !state.reached.two) { celebrate('✔  Quorum reached — two-thirds'); }
        }
        state.reached.maj = s.majority_reached;
        state.reached.two = s.two_thirds_reached;
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
    document.addEventListener('fullscreenchange', function () { $('fs').textContent = document.fullscreenElement ? 'Exit full screen' : 'Full screen'; });

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
