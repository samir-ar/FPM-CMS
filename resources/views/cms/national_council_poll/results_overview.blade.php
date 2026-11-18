<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Results - National Council</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
@verbatim
<style>
    /* Same look as the live attendance board (council-meetings/{id}/board): the party's blue, the logo mark, glass
       cards, the green LIVE pill, orange for what matters. */
    :root {
        --bg1: #004a76; --bg2: #004e7c;
        --card: rgba(0, 24, 42, .14); --card-strong: rgba(0, 24, 42, .24);
        --line: rgba(247, 245, 240, .2);
        --text: #f7f5f0; --muted: #b3c9d9; --faint: #86a3b8;
        --orange: #f48d24; --gold: #f48d24; --blue: #1f86bd; --sky: #5fbbe6;
        --green: #34e0a0; --red: #ff7a7a; --radius: 22px;
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    html { min-height: 100%; background: #004a76; }
    body { min-height: 100vh; }
    body {
        font-family: 'Inter', 'Tajawal', 'Segoe UI', system-ui, sans-serif; color: var(--text);
        background:
            radial-gradient(1200px 700px at 12% -10%, rgba(244, 141, 36, .06), transparent 60%),
            radial-gradient(1000px 800px at 105% 10%, rgba(0, 78, 124, .6), transparent 55%),
            linear-gradient(160deg, var(--bg2), var(--bg1) 70%);
        background-attachment: fixed; overflow-x: hidden; font-variant-numeric: tabular-nums;
    }
    body::before {
        content: ''; position: fixed; inset: 0; pointer-events: none; opacity: .5;
        background-image: radial-gradient(rgba(255,255,255,.07) 1px, transparent 1px); background-size: 28px 28px;
        mask-image: radial-gradient(ellipse at center, #000 30%, transparent 80%);
    }
    bdi, .auto { unicode-bidi: plaintext; }

    /* ---------- header (same as the attendance board) ---------- */
    header { position: relative; z-index: 2; display: flex; align-items: center; justify-content: space-between; gap: 24px; padding: clamp(14px, 1.6vw, 26px) clamp(18px, 2.4vw, 44px); }
    .brand { display: flex; align-items: center; gap: 18px; min-width: 0; }
    .mark { flex: none; width: 76px; height: 76px; border-radius: 18px; overflow: hidden; background: #004e7c; box-shadow: 0 10px 30px rgba(0,0,0,.4), 0 0 0 3px rgba(247,245,240,.55); }
    .mark img { display: block; width: 100%; height: 100%; transform: scale(1.35); }
    .kicker { font-size: 12px; font-weight: 600; letter-spacing: .32em; text-transform: uppercase; color: var(--gold); }
    h1 { font-size: clamp(22px, 2.4vw, 40px); font-weight: 800; line-height: 1.1; margin-top: 4px; }
    .sub { color: var(--muted); font-size: 14px; margin-top: 4px; }
    .tools { display: flex; align-items: center; gap: 12px; flex: none; }
    .live { user-select: none; display: inline-flex; align-items: center; gap: 9px; padding: 9px 16px; border-radius: 999px; background: rgba(52,224,160,.12); border: 1px solid rgba(52,224,160,.35); font-size: 13px; font-weight: 700; letter-spacing: .14em; color: var(--green); }
    .live i { width: 9px; height: 9px; border-radius: 50%; background: var(--green); box-shadow: 0 0 0 0 rgba(52,224,160,.7); animation: ping 1.8s infinite; }
    .live.paused { background: rgba(255,255,255,.07); border-color: var(--line); color: var(--muted); }
    .live.paused i { background: var(--muted); animation: none; }
    .live.lost { background: rgba(255,122,122,.12); border-color: rgba(255,122,122,.4); color: var(--red); }
    .live.lost i { background: var(--red); animation: none; }
    @keyframes ping { 0% { box-shadow: 0 0 0 0 rgba(52,224,160,.6); } 70% { box-shadow: 0 0 0 12px rgba(52,224,160,0); } 100% { box-shadow: 0 0 0 0 rgba(52,224,160,0); } }
    #clock { font-size: 22px; font-weight: 600; letter-spacing: .04em; min-width: 98px; text-align: right; }
    .btn { cursor: pointer; text-decoration: none; color: var(--text); font: 600 13px 'Inter', sans-serif; padding: 10px 16px; border-radius: 12px; background: var(--card-strong); border: 1px solid var(--line); transition: background .2s, transform .2s; }
    .btn:hover { background: rgba(255,255,255,.16); transform: translateY(-1px); }

    main { position: relative; z-index: 1; display: grid; gap: clamp(14px, 1.3vw, 22px); padding: 0 clamp(18px, 2.4vw, 44px) clamp(24px, 3vw, 48px); }
    .card { background: var(--card); border: 1px solid var(--line); border-radius: var(--radius); backdrop-filter: blur(14px); -webkit-backdrop-filter: blur(14px); padding: clamp(16px, 1.5vw, 26px); box-shadow: 0 24px 60px rgba(0,0,0,.28), inset 0 1px 0 rgba(255,255,255,.06); }
    .card h2 { font-size: 12px; font-weight: 700; letter-spacing: .24em; text-transform: uppercase; color: var(--muted); margin-bottom: 16px; display: flex; justify-content: space-between; align-items: center; }

    /* ---------- poll picker ---------- */
    .picker { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; }
    .chip { position: relative; cursor: pointer; }
    .chip input { position: absolute; opacity: 0; inset: 0; cursor: pointer; }
    .chip span { display: inline-block; padding: 9px 18px; border-radius: 999px; background: rgba(255,255,255,.06); border: 1px solid var(--line); font-size: 14px; font-weight: 600; color: var(--muted); transition: all .2s; }
    .chip small { margin-inline-start: 8px; font-weight: 500; color: var(--faint); font-size: 12px; }
    .chip:hover span { background: rgba(255,255,255,.12); color: var(--text); }
    .chip input:checked + span { background: var(--orange); border-color: var(--orange); color: #2b1500; box-shadow: 0 0 18px rgba(244,141,36,.45); }
    .chip input:checked + span small { color: rgba(43,21,0,.7); }
    .chip input:focus-visible + span { outline: 2px solid #fff; outline-offset: 2px; }
    .sp { flex: 1; }
    .topn { display: inline-flex; align-items: center; gap: 10px; font-size: 13px; color: var(--muted); font-weight: 600; }
    .topn input { width: 64px; padding: 9px 10px; text-align: center; border-radius: 12px; background: var(--card-strong); border: 1px solid var(--line); color: var(--text); font: 600 14px 'Inter', sans-serif; }

    /* ---------- one poll ---------- */
    .poll { display: grid; gap: clamp(14px, 1.3vw, 22px); grid-template-columns: repeat(12, 1fr); animation: rise .6s cubic-bezier(.2,.8,.2,1) both; margin-bottom: 8px; }
    @keyframes rise { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: none; } }
    .poll-head { grid-column: 1 / -1; display: flex; align-items: baseline; justify-content: space-between; gap: 16px; padding: 14px 4px 0; }
    .poll-head h3 { font-size: clamp(22px, 2vw, 32px); font-weight: 800; line-height: 1.2; }
    .poll-head span { color: var(--muted); font-size: 13px; }
    .hero { grid-column: span 4; display: flex; flex-direction: column; align-items: center; }
    .gauge { cursor: pointer; position: relative; width: min(100%, 250px); aspect-ratio: 1; margin: 2px auto 8px; }
    .gauge svg { width: 100%; height: 100%; overflow: visible; }
    .gauge .track { fill: none; stroke: rgba(255,255,255,.08); stroke-width: 24; }
    .gauge .arc { fill: none; stroke-width: 24; stroke-linecap: round; stroke: var(--orange); transition: stroke-dashoffset 1.4s cubic-bezier(.2,.8,.2,1); }
    .gauge .arc.glow { filter: blur(11px); opacity: .55; }
    .gauge .mid { position: absolute; inset: 0; display: grid; place-items: center; text-align: center; }
    .big { font-size: clamp(40px, 3.8vw, 68px); font-weight: 800; line-height: 1; letter-spacing: -.03em; background: linear-gradient(180deg, #fff, #ffd9a1); -webkit-background-clip: text; background-clip: text; color: transparent; }
    .gauge .mid small { display: block; margin-top: 6px; font-size: 11px; letter-spacing: .22em; text-transform: uppercase; color: var(--muted); font-weight: 700; }
    .kpis { grid-column: span 8; align-self: stretch; display: grid; grid-template-columns: repeat(3, 1fr); gap: clamp(10px, 1vw, 16px); }
    .kpi { position: relative; overflow: hidden; padding: clamp(16px, 1.4vw, 24px); border-radius: var(--radius); background: var(--card); border: 1px solid var(--line); box-shadow: 0 18px 40px rgba(0,0,0,.25), inset 0 1px 0 rgba(255,255,255,.06); display: flex; flex-direction: column; justify-content: center; }
    .kpi::after { content: ''; position: absolute; right: -30px; top: -30px; width: 110px; height: 110px; border-radius: 50%; background: var(--accent, var(--orange)); opacity: .16; filter: blur(8px); }
    .kpi .l { font-size: 11px; letter-spacing: .2em; text-transform: uppercase; color: var(--muted); font-weight: 700; margin-bottom: 10px; }
    .kpi .n { font-size: clamp(34px, 3.2vw, 56px); font-weight: 800; line-height: 1; letter-spacing: -.03em; }
    .kpi .s { margin-top: 10px; font-size: 13px; color: var(--muted); }

    /* ---------- a question ---------- */
    .q { grid-column: 1 / -1; }
    .q-title { font-size: clamp(18px, 1.6vw, 26px); font-weight: 800; line-height: 1.25; }
    .q-sub { margin: 4px 0 6px; font-size: 13px; color: var(--muted); }
    .tie { display: inline-block; margin-inline-start: 12px; padding: 3px 12px; border-radius: 99px; background: rgba(244,141,36,.16); border: 1px solid rgba(244,141,36,.4); color: #ffc88a; font-size: 12px; font-weight: 700; }

    .podium { display: flex; justify-content: center; align-items: flex-end; gap: clamp(18px, 3vw, 48px); padding: 18px 0 26px; flex-wrap: wrap; }
    .pod { text-align: center; width: clamp(150px, 16vw, 230px); animation: rise .7s cubic-bezier(.2,.8,.2,1) both; }
    .pod .face { position: relative; margin: 0 auto 14px; }
    .pod.p1 .face { width: clamp(110px, 10vw, 150px); height: clamp(110px, 10vw, 150px); }
    .pod.p2 .face, .pod.p3 .face { width: clamp(84px, 7.6vw, 112px); height: clamp(84px, 7.6vw, 112px); }
    .pod .badge { position: absolute; inset-inline-end: -4px; bottom: -2px; width: 36px; height: 36px; border-radius: 50%; display: grid; place-items: center; font-weight: 800; font-size: 16px; background: #003a5e; border: 2px solid rgba(255,255,255,.4); }
    .pod.p1 .badge, .pod.win .badge { border-color: var(--orange); color: #ffc88a; }
    .pod.p1 .face::before { content: ''; position: absolute; inset: -10px; border-radius: 50%; background: radial-gradient(circle, rgba(244,141,36,.5), transparent 68%); filter: blur(10px); z-index: -1; }
    .podium { flex-direction: column; align-items: center; row-gap: 30px; }
    .prow { display: flex; justify-content: center; align-items: flex-end; gap: clamp(18px, 3vw, 48px); flex-wrap: wrap; }
    .pbreak { flex-basis: 100%; height: 0; }
    .pod.pn { width: clamp(130px, 13vw, 190px); }
    .pod.pn .face { width: clamp(72px, 6.4vw, 96px); height: clamp(72px, 6.4vw, 96px); }
    .pod.pn .w { font-size: clamp(26px, 2.4vw, 38px); }
    .pod.pn .nm { font-size: 15px; }
    .pod .nm { font-size: 16px; font-weight: 700; line-height: 1.3; margin-bottom: 8px; unicode-bidi: plaintext; }
    .pod.p1 .nm { font-size: 19px; }
    .pod .w { font-size: clamp(32px, 3vw, 48px); font-weight: 800; line-height: 1; letter-spacing: -.03em; }
    .pod.p1 .w { font-size: clamp(42px, 4vw, 64px); background: linear-gradient(180deg, #fff, #ffd9a1); -webkit-background-clip: text; background-clip: text; color: transparent; }
    .pod .pc { margin-top: 8px; font-size: 13px; color: var(--muted); }

    .av { width: 100%; height: 100%; border-radius: 50%; overflow: hidden; display: grid; place-items: center; font-weight: 800; color: #2b1500; background: linear-gradient(145deg, #ffd08a, var(--orange)); border: 2px solid rgba(255,255,255,.35); }
    .av img { width: 100%; height: 100%; object-fit: cover; object-position: center top; display: block; }
    .win .av, .p1 .av, .row.win .av, .row.lead .av { border-color: var(--orange); }

    .rows { display: grid; gap: 6px; }
    .row { display: grid; grid-template-columns: 34px 52px minmax(160px, 300px) 1fr 200px; align-items: center; gap: 16px; padding: 11px 16px; border-radius: 16px; background: rgba(255,255,255,.045); border: 1px solid transparent; transition: border-color .5s, background .5s; }
    .row:hover { background: rgba(255,255,255,.09); }
    .row.win, .row.lead { background: rgba(244,141,36,.1); border-color: rgba(244,141,36,.38); }
    .rank { text-align: center; font-weight: 800; color: var(--faint); font-size: 16px; }
    .row.win .rank, .row.lead .rank { color: #ffc88a; }
    .row .face { width: 52px; height: 52px; }
    .row .nm { font-size: 17px; font-weight: 600; line-height: 1.3; text-align: left; unicode-bidi: plaintext; }
    .row.win .nm, .row.lead .nm { font-weight: 800; }
    .bar { height: 12px; border-radius: 99px; background: rgba(255,255,255,.09); overflow: hidden; }
    .bar i { display: block; height: 100%; width: 0; border-radius: 99px; background: linear-gradient(90deg, var(--blue), var(--sky)); transition: width 1.2s cubic-bezier(.2,.8,.2,1); min-width: 4px; position: relative; overflow: hidden; }
    .row.win .bar i, .row.lead .bar i { background: linear-gradient(90deg, #f48d24, #ffb25c); }
    .bar i::after { content: ''; position: absolute; inset: 0; background: linear-gradient(100deg, transparent 30%, rgba(255,255,255,.45) 50%, transparent 70%); transform: translateX(-100%); animation: sweep 3.2s ease-in-out infinite; }
    @keyframes sweep { 60%, 100% { transform: translateX(100%); } }
    .fig { text-align: end; white-space: nowrap; color: var(--muted); font-size: 13px; }
    .fig b { color: var(--text); font-size: 24px; font-weight: 800; margin-inline-end: 8px; letter-spacing: -.02em; }
    .cut { display: flex; align-items: center; gap: 14px; margin: 8px 0; color: #ffc88a; font-size: 11px; letter-spacing: .24em; text-transform: uppercase; font-weight: 700; }
    .cut::before, .cut::after { content: ''; flex: 1; height: 1px; background: linear-gradient(90deg, transparent, rgba(244,141,36,.7), transparent); }
    .wait { text-align: center; color: var(--muted); padding: 22px 0 6px; font-size: 15px; }
    .empty { text-align: center; color: var(--muted); padding: 90px 0; font-size: 16px; }
    .empty b { display: block; font-size: 24px; color: var(--text); margin-bottom: 8px; }

    /* ---------- "choose a poll" cards ---------- */
    .pcards { display: flex; flex-wrap: wrap; justify-content: center; gap: clamp(16px, 1.6vw, 28px); }
    .pcard { width: min(100%, 400px); text-align: center; align-items: center; cursor: pointer; color: var(--text); font-family: inherit; padding: 22px 24px; border-radius: var(--radius); background: var(--card); border: 1px solid var(--line); backdrop-filter: blur(14px); -webkit-backdrop-filter: blur(14px); box-shadow: 0 24px 60px rgba(0,0,0,.28), inset 0 1px 0 rgba(255,255,255,.06); transition: transform .2s, border-color .2s, background .2s; display: flex; flex-direction: column; gap: 16px; }
    .pcard:hover { transform: translateY(-4px); border-color: rgba(244,141,36,.7); background: rgba(0,24,42,.26); }
    .pcard:focus-visible { outline: 2px solid #fff; outline-offset: 3px; }
    .pcard .t { font-size: 22px; font-weight: 800; line-height: 1.25; unicode-bidi: plaintext; }
    .pcard .tags { display: flex; gap: 8px; flex-wrap: wrap; justify-content: center; }
    .pcard .tag { padding: 3px 11px; border-radius: 99px; font-size: 11.5px; font-weight: 700; letter-spacing: .04em; background: rgba(255,255,255,.08); border: 1px solid var(--line); color: var(--muted); }
    .pcard .tag.ok { color: var(--green); background: rgba(52,224,160,.1); border-color: rgba(52,224,160,.35); }
    .pcard .tag.or { color: #ffc88a; background: rgba(244,141,36,.14); border-color: rgba(244,141,36,.4); }
    .pcard .st { display: flex; justify-content: center; gap: 36px; width: 100%; text-align: center; }
    .pcard .st b { display: block; font-size: 26px; font-weight: 800; letter-spacing: -.02em; }
    .pcard .st span { font-size: 11px; letter-spacing: .14em; text-transform: uppercase; color: var(--muted); font-weight: 700; }
    .pcard .go { margin-top: auto; color: #ffc88a; font-weight: 700; font-size: 14px; }
    .pick-title { font-size: clamp(26px, 2.4vw, 38px); font-weight: 800; margin: 18px 0 4px; text-align: center; }
    .pick-sub { color: var(--muted); margin-bottom: 28px; text-align: center; }

    /* ---------- interactive bits ---------- */
    .qtools { display: none !important; }
    .qtools-old { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; margin: 10px 0 6px; }
    .qsearch { flex: 0 1 280px; padding: 9px 14px; border-radius: 12px; background: var(--card-strong); border: 1px solid var(--line); color: var(--text); font: 500 14px 'Inter', 'Tajawal', sans-serif; }
    .qsearch::placeholder { color: var(--faint); }
    .tgl { cursor: pointer; padding: 9px 16px; border-radius: 12px; background: var(--card-strong); border: 1px solid var(--line); color: var(--muted); font: 700 13px 'Inter', sans-serif; transition: all .2s; }
    .tgl:hover { color: var(--text); }
    .tgl.on { background: var(--orange); border-color: var(--orange); color: #2b1500; }
    .row.fx { display: none; }
    .rows:hover .row:not(:hover) { opacity: .55; }
    .row:hover { transform: translateX(4px); }
    .elected { margin: 12px 0 8px; padding: 18px 20px; border-radius: 18px; background: rgba(244,141,36,.08); border: 1px solid rgba(244,141,36,.3); }
    .elt-l { font-size: 12px; font-weight: 800; letter-spacing: .24em; text-transform: uppercase; color: #ffc88a; margin-bottom: 14px; text-align: center; }
    .elt-list { display: flex; flex-wrap: wrap; justify-content: center; gap: 14px 22px; }
    .elt { display: flex; align-items: center; gap: 12px; cursor: pointer; padding: 6px 14px 6px 6px; border-radius: 999px; background: rgba(255,255,255,.07); border: 1px solid var(--line); transition: background .2s, transform .2s; }
    .elt:hover { background: rgba(255,255,255,.14); transform: translateY(-2px); }
    .elt .face { width: 46px; height: 46px; flex: none; }
    .elt .nm { font-size: 15px; font-weight: 700; unicode-bidi: plaintext; }
    .elt.tie { border-style: dashed; color: #ffc88a; cursor: default; padding: 12px 18px; }
    .qnav { grid-column: 1 / -1; display: flex; flex-wrap: wrap; gap: 8px; }
    .qnav button { cursor: pointer; padding: 8px 16px; border-radius: 999px; background: var(--card-strong); border: 1px solid var(--line); color: var(--muted); font: 700 13px 'Inter', 'Tajawal', sans-serif; max-width: 360px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; transition: all .2s; unicode-bidi: plaintext; }
    .qnav button:hover { background: var(--orange); border-color: var(--orange); color: #2b1500; }
    /* before the reveal only the summary (turnout, counters) is on screen; the candidates appear when it starts */
    body:not(.reveal) .qs, body:not(.reveal) .qnav { display: none !important; }
    body.reveal:not(.finished) .qtools, body.reveal:not(.finished) .qnav { display: none; }
    body.reveal .q:not(.done) .elected { display: none !important; }

    /* ---------- reveal mode (the presenter uncovers the results step by step) ---------- */
    .btn.go { background: var(--orange); border-color: var(--orange); color: #2b1500; font-weight: 800; box-shadow: 0 0 22px rgba(244,141,36,.45); }
    .btn.go:hover { background: #ffa443; }
    .rh { opacity: 0; transform: translateY(22px) scale(.97); pointer-events: none; }
    .row, .pod, .cut { transition: opacity .7s cubic-bezier(.2,.8,.2,1), transform .7s cubic-bezier(.2,.8,.2,1), border-color .5s, background .5s; }
    .pod.rh { transform: translateY(40px) scale(.8); animation: none; opacity: 0; }
    /* during the reveal the list starts empty at the last place and grows downward; nothing hidden takes space */
    body.reveal:not(.finished) .rows .rh { display: none; }
    body.reveal:not(.finished) .pod.rh { display: none; }
    body.reveal:not(.finished) .prow:not(:has(.pod:not(.rh))) { display: none; }
    body.reveal:not(.finished) .q:not(.podon) .podium { display: none; }
    .row.pop, .pod.pop { animation: pop .9s cubic-bezier(.2,1.2,.3,1); }
    @keyframes pop { 0% { box-shadow: 0 0 0 0 rgba(244,141,36,.8); } 100% { box-shadow: 0 0 0 26px rgba(244,141,36,0); } }
    .ctl { position: fixed; z-index: 30; left: 50%; bottom: 22px; transform: translateX(-50%); display: none; align-items: center; gap: 10px; padding: 10px 12px; border-radius: 999px; background: rgba(0,24,42,.72); border: 1px solid var(--line); backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px); box-shadow: 0 18px 50px rgba(0,0,0,.45); }
    body.reveal .ctl { display: none !important; }
    .ctl .btn { padding: 10px 20px; border-radius: 999px; }
    .ctl .count { min-width: 64px; text-align: center; font-weight: 700; color: var(--muted); font-size: 13px; }
    .ctl .hint { color: var(--faint); font-size: 12px; padding: 0 8px; }
    .ctl .btn.on { background: rgba(52,224,160,.2); border-color: rgba(52,224,160,.5); color: var(--green); }
    body.reveal .picker { opacity: .35; pointer-events: none; }
    #fx { position: fixed; inset: 0; z-index: 40; pointer-events: none; }
    .spot { position: fixed; inset: 0; z-index: 50; display: none; align-items: center; justify-content: center; background: rgba(0,18,32,.72); backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); cursor: pointer; }
    .spot.on { display: flex; animation: rise .35s ease both; }
    .spot .box { text-align: center; padding: 34px 48px; border-radius: 30px; background: var(--card-strong); border: 1px solid var(--line); box-shadow: 0 40px 90px rgba(0,0,0,.5); min-width: min(90vw, 460px); }
    .spot .face { width: 190px; height: 190px; margin: 0 auto 20px; position: relative; }
    .spot .face .av { border-width: 4px; border-color: var(--orange); font-size: 70px; }
    .spot .r { color: #ffc88a; letter-spacing: .26em; font-size: 12px; font-weight: 700; text-transform: uppercase; }
    .spot .n { font-size: clamp(26px, 3vw, 40px); font-weight: 800; margin: 8px 0 4px; unicode-bidi: plaintext; }
    .spot .qq { color: var(--muted); font-size: 14px; margin-bottom: 18px; unicode-bidi: plaintext; }
    .spot .big { font-size: 84px; }
    .spot .meta { color: var(--muted); margin-top: 10px; font-size: 15px; }
    .row, .pod { cursor: pointer; }
    .agetag { display: inline-block; margin-inline-start: 10px; padding: 2px 9px; border-radius: 99px; font-size: 11px; font-weight: 700; letter-spacing: .04em; color: #ffc88a; background: rgba(244,141,36,.16); border: 1px solid rgba(244,141,36,.4); vertical-align: middle; }
    .agetag.dim { color: var(--muted); background: rgba(255,255,255,.06); border-color: var(--line); }

    @media (max-width: 1100px) {
        header { flex-wrap: wrap; } .hero, .kpis { grid-column: 1 / -1; } .kpis { grid-template-columns: 1fr; }
        .row { grid-template-columns: 28px 44px 1fr 120px; } .bar { grid-column: 2 / 5; grid-row: 2; }
        h1 { white-space: normal; }
    }
    @media (prefers-reduced-motion: reduce) { * { animation: none !important; transition: none !important; } }
</style>
@endverbatim
</head>
<body>
<div id="stage">
<header>
    <div class="brand">
        <div class="mark"><img src="{{ asset('council-board/fpm-logo.png') }}" alt=""></div>
        <div style="min-width:0">
            <div class="kicker">National Council &middot; Results</div>
            <h1 class="auto" id="title">Results</h1>
            <div class="sub" id="subtitle"></div>
        </div>
    </div>
    <div class="tools">
        <div class="live paused" id="live"><i></i><span id="liveText">RESULTS</span></div>
        <div id="clock">--:--:--</div>
        <button class="btn" id="refreshBtn" type="button" style="display:none">Refresh</button>
        <button class="btn" id="changeBtn" type="button" style="display:none">Change poll</button>
        <button class="btn go" id="revealBtn" type="button">Start reveal</button>
        <button class="btn" id="fs" type="button">Full screen</button>
        <a class="btn" href="{{ route('admin.national-council-poll.index') }}">Back</a>
    </div>
</header>

<main>
    <section class="card" id="pickerCard" style="display:none !important;">
        <div class="picker" id="picker">
            <span class="sub" style="margin:0 6px 0 0;">Poll</span>
            @foreach($polls as $p)
                <label class="chip">
                    <input type="radio" name="poll" value="{{ $p->id }}" @if(in_array($p->id, $chosen)) checked @endif>
                    <span><bdi>{{ $p->title }}</bdi></span>
                </label>
            @endforeach
            <span class="sp"></span>
            <label class="topn" title="Leave empty to use the number of winners set on the poll">Winners shown <input type="number" id="top" min="0" value="{{ $top ?: '' }}" placeholder="auto"></label>
        </div>
    </section>

    <div id="chooser">
        <div class="pick-title">Choose a poll</div>
        <div class="pick-sub">Select the poll whose results you want to open.</div>
        @if($polls->isEmpty())
            <div class="sub">There are no polls yet.</div>
        @endif
        <div class="pcards">
            @foreach($polls as $p)
                @php $closed = $p->closes_at && \Carbon\Carbon::parse($p->closes_at)->isPast(); @endphp
                <button type="button" class="pcard" data-id="{{ $p->id }}">
                    <div class="t">{{ $p->title }}</div>
                    <div class="tags">
                        <span class="tag {{ $p->is_published ? 'ok' : '' }}">{{ $p->is_published ? 'Published' : 'Not published' }}</span>
                        @if($closed)<span class="tag">Closed</span>@endif
                        @if($p->winners)<span class="tag or">{{ $p->winners }} {{ $p->winners == 1 ? 'winner' : 'winners' }}</span>@endif
                    </div>
                    <div class="st">
                        <div><b>{{ $p->electors }}</b><span>Voters</span></div>
                        <div><b>{{ $p->question_count }}</b><span>Questions</span></div>
                    </div>
                    <div class="go">Open results &rarr;</div>
                </button>
            @endforeach
        </div>
    </div>

    <div id="stages"></div>
</main>
</div>

<canvas id="fx"></canvas>
<div class="spot" id="spot"><div class="box" id="spotBox"></div></div>
<div class="ctl" id="ctl">
    <button class="btn" id="ctlReset" type="button" title="Start over">Restart</button>
    <button class="btn go" id="ctlNext" type="button">Next &rarr;</button>
    <button class="btn" id="ctlAuto" type="button">Auto</button>
    <span class="count" id="ctlCount"></span>
    <span class="hint">Space / &rarr; next &middot; Esc exit</span>
    <button class="btn" id="ctlExit" type="button">Exit</button>
</div>

<script>
(function () {
    var cfg = {
        initial: {!! json_encode($initial, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!},
        dataUrl: {!! json_encode($dataUrl) !!},
        every: 5000
    };
    var stages = document.getElementById('stages'), chooser = document.getElementById('chooser'), pickerCard = document.getElementById('pickerCard');
    var liveBtn = document.getElementById('live'), liveText = document.getElementById('liveText');
    var topInput = document.getElementById('top'), titleEl = document.getElementById('title'), subEl = document.getElementById('subtitle');
    var live = false, timer = null, reduce = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
    var CIRC = 2 * Math.PI * 100;   // ring radius 100 in a 240 box

    function el(tag, cls, text) { var e = document.createElement(tag); if (cls) e.className = cls; if (text != null) e.textContent = text; return e; }
    function fmt(n) { return Number(n).toLocaleString('en-US'); }
    function votes(n) { return n + (n === 1 ? ' vote' : ' votes'); }

    function count(node, to, ms) {
        ms = ms || 900;
        to = Number(to) || 0;
        var from = Number(node.getAttribute('data-v')); if (isNaN(from)) from = 0;
        node.setAttribute('data-v', to);
        if (reduce || from === to) { node.textContent = fmt(to); return; }
        var t0 = null;
        function step(ts) {
            if (t0 === null) t0 = ts;
            var k = Math.min(1, (ts - t0) / ms), e = 1 - Math.pow(1 - k, 3);
            node.textContent = fmt(Math.round(from + (to - from) * e));
            if (k < 1) requestAnimationFrame(step);
        }
        requestAnimationFrame(step);
    }

    function avatar(name, photo) {
        var wrap = el('div', 'av'), initial = (name || '?').trim().charAt(0);
        if (photo) {
            var img = new Image(); img.alt = ''; img.loading = 'lazy';
            img.onerror = function () { wrap.textContent = initial; img.remove(); };
            img.src = photo; wrap.appendChild(img);
        } else { wrap.textContent = initial; }
        return wrap;
    }
    function isWin(r, top) { return top ? (r.rank <= top && r.weight > 0) : false; }
    function isLead(r, top, cast) { return !top && cast > 0 && r.rank === 1; }

    // The winners' podium: with a number of winners set it holds ALL of them (first row = 2nd, 1st, 3rd as a
    // podium, the rest below); without one it is the top three.
    function renderPodium(box, q, top) {
        var rows = top ? q.rows.filter(function (r) { return isWin(r, top); }) : q.rows.filter(function (r) { return r.weight > 0; }).slice(0, 3);
        rows = rows.slice(0, 12);
        var sig = rows.map(function (r) { return r.id + ':' + r.weight; }).join('|') + '#' + top;
        if (box.getAttribute('data-sig') === sig) return;
        box.setAttribute('data-sig', sig); box.textContent = '';
        function make(r, i) {
            var pod = el('div', 'pod ' + (i < 3 ? 'p' + (i + 1) : 'pn') + (isWin(r, top) ? ' win' : '')); pod.setAttribute('data-id', r.id); pod.setAttribute('data-pos', i);
            var face = el('div', 'face'); face.appendChild(avatar(r.name, r.photo)); face.appendChild(el('div', 'badge', r.rank));
            pod.appendChild(face); pod.appendChild(el('div', 'nm', r.name));
            var w = el('div', 'w'); count(w, r.weight); pod.appendChild(w);
            pod.appendChild(el('div', 'pc', votes(r.votes) + ' \u00b7 ' + r.pct + '%'));
            return pod;
        }
        // a tree: 1st alone on top, then 2nd+3rd, then 4th-6th, then 7th-10th ...
        var i = 0, size = 1;
        while (i < rows.length) {
            var pr = el('div', 'prow');
            rows.slice(i, i + size).forEach(function (r, j) { pr.appendChild(make(r, i + j)); });
            box.appendChild(pr);
            i += size; size++;
        }
    }

    function renderRows(list, q, top) {
        var max = 1; q.rows.forEach(function (r) { if (r.weight > max) max = r.weight; });
        var existing = {}; Array.prototype.forEach.call(list.querySelectorAll('.row'), function (n) { existing[n.getAttribute('data-id')] = n; });
        var before = {}; Object.keys(existing).forEach(function (k) { before[k] = existing[k].getBoundingClientRect().top; });
        Array.prototype.forEach.call(list.querySelectorAll('.cut'), function (n) { n.remove(); });
        var seen = {};
        q.rows.forEach(function (r, idx) {
            var node = existing[r.id], fresh = !node;
            if (fresh) {
                node = el('div', 'row'); node.setAttribute('data-id', r.id);
                node.appendChild(el('div', 'rank'));
                var face = el('div', 'face'); face.appendChild(avatar(r.name, r.photo)); node.appendChild(face);
                node.appendChild(el('div', 'nm'));
                var bar = el('div', 'bar'); bar.appendChild(el('i')); node.appendChild(bar);
                var fig = el('div', 'fig'); fig.appendChild(el('b')); fig.appendChild(el('span')); node.appendChild(fig);
            }
            seen[r.id] = true;
            node.className = 'row' + (isWin(r, top) ? ' win' : '') + (isLead(r, top, q.cast_weight) ? ' lead' : '');
            node.children[0].textContent = r.rank;
            node.children[2].textContent = r.name;
            if (r.won_by_age) node.children[2].appendChild(el('span', 'agetag', 'older – wins the tie'));
            else if (r.tied_with_older) node.children[2].appendChild(el('span', 'agetag dim', 'tie – older ranked first'));
            count(node.querySelector('.fig b'), r.weight);
            node.querySelector('.fig span').textContent = votes(r.votes) + ' · ' + r.pct + '%';
            node.title = r.name + ' — weight ' + r.weight + ', ' + votes(r.votes) + ', ' + r.pct + '% of the weight';
            list.appendChild(node);
            var w = (r.weight * 100 / max) + '%', bi = node.querySelector('.bar i');
            if (fresh) { requestAnimationFrame(function () { requestAnimationFrame(function () { bi.style.width = w; }); }); } else { bi.style.width = w; }
            var next = q.rows[idx + 1];
            if (top && r.rank <= top && next && next.rank > top) { list.appendChild(el('div', 'cut', 'Cut-off · top ' + top)); }
        });
        Object.keys(existing).forEach(function (k) { if (!seen[k]) existing[k].remove(); });
        if (!reduce) {
            Object.keys(before).forEach(function (k) {
                var n = list.querySelector('.row[data-id="' + k + '"]'); if (!n) return;
                var dy = before[k] - n.getBoundingClientRect().top;
                if (Math.abs(dy) > 1) {
                    n.style.transition = 'none'; n.style.transform = 'translateY(' + dy + 'px)';
                    requestAnimationFrame(function () { requestAnimationFrame(function () { n.style.transition = 'transform .7s cubic-bezier(.2,.8,.2,1), border-color .5s, background .5s'; n.style.transform = ''; }); });
                }
            });
        }
    }

    // "Elected" strip: who holds the seats (only when the poll has a number of winners)
    function renderElected(box, q, top) {
        var win = q.rows.filter(function (r) { return isWin(r, top); });
        if (!top || !win.length || q.cast_weight <= 0) { box.style.display = 'none'; return; }
        box.style.display = ''; box.textContent = '';
        box.appendChild(el('div', 'elt-l', 'Elected \u00b7 ' + top + (top === 1 ? ' seat' : ' seats')));
        var list = el('div', 'elt-list');
        win.forEach(function (r) {
            var it = el('div', 'elt'); it.setAttribute('data-id', r.id);
            var f = el('div', 'face'); f.appendChild(avatar(r.name, r.photo)); it.appendChild(f); it.appendChild(el('div', 'nm', r.name));
            list.appendChild(it);
        });
        if (q.tie_at_cutoff) list.appendChild(el('div', 'elt tie', 'Last seat tied \u2013 a decision is needed'));
        box.appendChild(list);
    }
    // search box + "Winners only" toggle of one question
    function applyFilter(node) {
        var term = node.querySelector('.qsearch').value.trim().toLowerCase();
        var only = node.querySelector('.tgl').classList.contains('on');
        Array.prototype.forEach.call(node.querySelectorAll('.row'), function (row) {
            var hide = (term && row.querySelector('.nm').textContent.toLowerCase().indexOf(term) < 0) || (only && !row.classList.contains('win') && !row.classList.contains('lead'));
            row.classList.toggle('fx', !!hide);
        });
        Array.prototype.forEach.call(node.querySelectorAll('.cut'), function (c) { c.style.display = (term || only) ? 'none' : ''; });
    }

    function renderQuestion(wrap, q, top) {
        var node = wrap.querySelector('[data-q="' + q.id + '"]');
        if (!node) {
            node = el('section', 'card q'); node.setAttribute('data-q', q.id);
            node.appendChild(el('div', 'q-title')); node.appendChild(el('div', 'q-sub'));
            node.appendChild(el('div', 'elected'));
            var tools = el('div', 'qtools');
            var inp = el('input', 'qsearch'); inp.type = 'search'; inp.placeholder = 'Find a candidate\u2026';
            var tg = el('button', 'tgl', 'Winners only'); tg.type = 'button';
            inp.addEventListener('input', function () { applyFilter(node); });
            tg.addEventListener('click', function () { tg.classList.toggle('on'); applyFilter(node); });
            tools.appendChild(inp); tools.appendChild(tg); node.appendChild(tools);
            node.appendChild(el('div', 'podium')); node.appendChild(el('div', 'rows')); node.appendChild(el('div', 'wait'));
            wrap.appendChild(node);
        }
        node.querySelector('.q-title').textContent = q.text;
        var sub = node.querySelector('.q-sub');
        sub.textContent = 'Weight cast ' + fmt(q.cast_weight);
        if (q.tie_at_cutoff) sub.appendChild(el('span', 'tie', 'Tie at the cut-off – a decision is needed'));
        var pod = node.querySelector('.podium');
        if (q.cast_weight > 0) { renderPodium(pod, q, top); pod.style.display = ''; } else { pod.style.display = 'none'; }
        renderRows(node.querySelector('.rows'), q, top);
        renderElected(node.querySelector('.elected'), q, top);
        applyFilter(node);
        node.querySelector('.wait').textContent = q.cast_weight > 0 ? '' : 'Waiting for the first vote…';
    }

    function turnoutText(v) { return String(v).replace(/\.0$/, ''); }
    function setRing(sec, turnout) {
        var off = CIRC * (1 - Math.min(100, turnout) / 100);
        Array.prototype.forEach.call(sec.querySelectorAll('.arc'), function (a) { a.style.strokeDashoffset = off; });
        sec.querySelector('[data-f="turnout"]').textContent = turnoutText(turnout);
    }
    function animateRing(sec) {
        var target = Math.min(100, Number(sec.__turnout) || 0);
        var arcs = sec.querySelectorAll('.arc'), num = sec.querySelector('[data-f="turnout"]');
        if (reduce) { setRing(sec, target); return; }
        Array.prototype.forEach.call(arcs, function (a) { a.style.transition = 'none'; a.style.strokeDashoffset = CIRC; });
        num.textContent = '0';
        void sec.offsetWidth;   // let the empty ring paint first, so the sweep is visible
        var ms = 4500, t0 = null, offFinal = CIRC * (1 - target / 100);
        function step(ts) {
            if (t0 === null) t0 = ts;
            var k = Math.min(1, (ts - t0) / ms), e = 1 - Math.pow(1 - k, 3);
            Array.prototype.forEach.call(arcs, function (a) { a.style.strokeDashoffset = CIRC - (CIRC - offFinal) * e; });
            num.textContent = k < 1 ? (Math.round(target * e * 10) / 10).toFixed(1) : turnoutText(target);
            if (k < 1) requestAnimationFrame(step);
        }
        requestAnimationFrame(step);
    }

    function renderPoll(p, top, showHead) {
        var sec = stages.querySelector('[data-poll="' + p.id + '"]');
        var isNew = !sec;
        if (!sec) {
            sec = el('div', 'poll'); sec.setAttribute('data-poll', p.id);
            var head = el('div', 'poll-head'); head.appendChild(el('h3', 'auto')); head.appendChild(el('span')); sec.appendChild(head);
            var hero = el('section', 'card hero');
            hero.innerHTML = '<h2 style="align-self:stretch">Turnout</h2><div class="gauge"><svg viewBox="0 0 240 240" aria-hidden="true"><g transform="rotate(-90 120 120)">' +
                '<circle class="track" cx="120" cy="120" r="100"/><circle class="arc glow" cx="120" cy="120" r="100" stroke-dasharray="' + CIRC + '" stroke-dashoffset="' + CIRC + '"/>' +
                '<circle class="arc main" cx="120" cy="120" r="100" stroke-dasharray="' + CIRC + '" stroke-dashoffset="' + CIRC + '"/></g></svg>' +
                '<div class="mid"><div><div class="big" data-f="turnout">0</div><small>% turnout</small></div></div></div>';
            sec.appendChild(hero);
            var k = el('div', 'kpis');
            [['voted', 'Voted', '--blue'], ['weight', 'Weight that voted', '--orange'], ['qs', 'Questions', '--sky']].forEach(function (s) {
                var c = el('div', 'kpi'); c.style.setProperty('--accent', 'var(' + s[2] + ')');
                c.appendChild(el('div', 'l', s[1])); var n = el('div', 'n'); n.setAttribute('data-f', s[0]); c.appendChild(n);
                var sm = el('div', 's'); sm.setAttribute('data-s', s[0]); c.appendChild(sm); k.appendChild(c);
            });
            sec.appendChild(k); sec.appendChild(el('div', 'qs')); sec.lastChild.style.cssText = 'grid-column:1/-1;display:grid;gap:clamp(14px,1.3vw,22px)';
            stages.appendChild(sec);
        }
        sec.querySelector('.poll-head h3').textContent = p.title;
        sec.querySelector('.poll-head span').textContent = (p.published ? 'Published' : 'Not published') + (p.closes_at ? ' · ' + (p.closed ? 'closed ' : 'closes ') + p.closes_at : '') + (p.top ? ' · ' + p.top + (p.top === 1 ? ' winner' : ' winners') : '');
        sec.querySelector('.poll-head').style.display = showHead ? '' : 'none';
        sec.__turnout = p.turnout;
        if (isNew) {
            // the ring sweeps from 0 up to the turnout while the number counts; click the ring to watch it again
            animateRing(sec);
            sec.querySelector('.gauge').addEventListener('click', function () { animateRing(sec); });
        } else {
            setRing(sec, p.turnout);
        }
        count(sec.querySelector('[data-f="voted"]'), p.voters, 3500);
        count(sec.querySelector('[data-f="weight"]'), p.voted_weight, 3500);
        count(sec.querySelector('[data-f="qs"]'), p.questions.length, 3500);
        sec.querySelector('[data-s="voted"]').textContent = 'of ' + fmt(p.electors) + ' allowed';
        sec.querySelector('[data-s="weight"]').textContent = 'of ' + fmt(p.total_weight) + ' total weight';
        sec.querySelector('[data-s="qs"]').textContent = '';
        var wrap = sec.querySelector('.qs');
        var nav = sec.querySelector('.qnav'); if (nav) nav.remove();
        if (p.questions.length > 1) {
            nav = el('div', 'qnav');
            p.questions.forEach(function (q) { var b = el('button', null, q.text); b.type = 'button'; b.addEventListener('click', function () { var t = wrap.querySelector('[data-q="' + q.id + '"]'); if (t) t.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'start' }); }); nav.appendChild(b); });
            sec.insertBefore(nav, wrap);
        }
        p.questions.forEach(function (q) { renderQuestion(wrap, q, top); });
        var keep = p.questions.map(function (q) { return String(q.id); });
        Array.prototype.forEach.call(wrap.querySelectorAll('[data-q]'), function (n) { if (keep.indexOf(n.getAttribute('data-q')) < 0) n.remove(); });
    }

    var info = {}, lastData = null;   // option id => what the spotlight shows
    function render(data) {
        lastData = data;
        data.polls.forEach(function (p) { p.questions.forEach(function (q) { q.rows.forEach(function (r) { info[r.id] = { r: r, q: q.text }; }); }); });
        var ids = data.polls.map(function (p) { return String(p.id); });
        Array.prototype.forEach.call(stages.querySelectorAll('[data-poll]'), function (n) { if (ids.indexOf(n.getAttribute('data-poll')) < 0) n.remove(); });
        data.polls.forEach(function (p) { renderPoll(p, p.top, data.polls.length > 1); });
        data.polls.forEach(function (p) { stages.appendChild(stages.querySelector('[data-poll="' + p.id + '"]')); });
        chooser.style.display = data.polls.length ? 'none' : '';
        var open = data.polls.length > 0;
        document.getElementById('revealBtn').style.display = (open && !revealing) ? '' : 'none';
        document.getElementById('changeBtn').style.display = (open && !revealing) ? '' : 'none';
        document.getElementById('refreshBtn').style.display = (open && !revealing) ? '' : 'none';
        titleEl.textContent = data.polls.length === 1 ? data.polls[0].title : (data.polls.length ? data.polls.length + ' polls' : 'Results');
        subEl.textContent = data.polls.length === 1 ? ((data.polls[0].published ? 'Published' : 'Not published') + (data.polls[0].closes_at ? ' · ' + (data.polls[0].closed ? 'closed ' : 'closes ') + data.polls[0].closes_at : '') + (data.polls[0].top ? ' · ' + data.polls[0].top + (data.polls[0].top === 1 ? ' winner' : ' winners') : '')) : (data.polls.length ? 'Results side by side' : '');
    }

    function chosen() { return Array.prototype.map.call(document.querySelectorAll('#picker input[name=poll]:checked'), function (c) { return c.value; }); }
    function query() { var q = chosen().map(function (v) { return 'polls[]=' + encodeURIComponent(v); }); var t = parseInt(topInput.value, 10); if (t > 0) q.push('top=' + t); return q.join('&'); }
    function setUrl() { history.replaceState(null, '', location.pathname + (query() ? '?' + query() : '')); }

    function refresh() {
        if (!chosen().length) { render({ polls: [], top: 0 }); return Promise.resolve(); }
        return fetch(cfg.dataUrl + '?' + query(), { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(function (r) { if (!r.ok) throw new Error(r.status); return r.json(); })
            .then(function (d) { liveBtn.classList.remove('lost'); liveText.textContent = 'RESULTS'; render(d); })
            .catch(function () { liveBtn.classList.add('lost'); liveText.textContent = 'COULD NOT LOAD'; });
    }
    // The voting is over when these results are shown, so nothing refreshes by itself (use Refresh to re-read).
    function schedule() {}

    document.getElementById('picker').addEventListener('change', function (e) { if (e.target === topInput) return; setUrl(); refresh(); });
    // a card in the "choose a poll" step opens that poll
    document.getElementById('chooser').addEventListener('click', function (e) {
        var c = e.target.closest('.pcard'); if (!c) return;
        var r = document.querySelector('#picker input[name=poll][value="' + c.getAttribute('data-id') + '"]');
        if (r) { r.checked = true; setUrl(); refresh(); }
    });
    var tt; topInput.addEventListener('input', function () { clearTimeout(tt); tt = setTimeout(function () { setUrl(); refresh(); }, 350); });
    document.getElementById('changeBtn').addEventListener('click', function () { location.href = location.pathname; });
    document.getElementById('refreshBtn').addEventListener('click', function () { refresh(); });
    function toggleFull() { if (document.fullscreenElement) document.exitFullscreen(); else document.documentElement.requestFullscreen && document.documentElement.requestFullscreen(); }
    document.getElementById('fs').addEventListener('click', toggleFull);
    document.addEventListener('keydown', function (e) { if ((e.key === 'f' || e.key === 'F') && !/INPUT|TEXTAREA/.test((e.target || {}).tagName || '')) toggleFull(); });
    function tick() { document.getElementById('clock').textContent = new Date().toLocaleTimeString('en-GB'); }
    tick(); setInterval(tick, 1000);

    /* ================= spotlight: click any candidate ================= */
    var spot = document.getElementById('spot'), spotBox = document.getElementById('spotBox');
    function openSpot(id) {
        var d = info[id]; if (!d) return;
        spotBox.textContent = '';
        var face = el('div', 'face'); face.appendChild(avatar(d.r.name, d.r.photo)); spotBox.appendChild(face);
        spotBox.appendChild(el('div', 'r', 'Place ' + d.r.rank));
        spotBox.appendChild(el('div', 'n', d.r.name));
        spotBox.appendChild(el('div', 'qq', d.q));
        spotBox.appendChild(el('div', 'big', fmt(d.r.weight)));
        spotBox.appendChild(el('div', 'meta', 'weight · ' + votes(d.r.votes) + ' · ' + d.r.pct + '% of the weight'));
        spot.classList.add('on');
    }
    spot.addEventListener('click', function () { spot.classList.remove('on'); });
    stages.addEventListener('click', function (e) {
        var n = e.target.closest('.row, .pod, .elt');
        if (n && !n.classList.contains('rh') && n.getAttribute('data-id')) openSpot(n.getAttribute('data-id'));
    });

    /* ================= confetti (no library) ================= */
    var fx = document.getElementById('fx'), ctx = fx.getContext('2d'), bits = [], raf = null;
    function confetti(x, y) {
        if (reduce) return;
        fx.width = innerWidth; fx.height = innerHeight;
        var cols = ['#f48d24', '#ffd08a', '#ffffff', '#5fbbe6', '#34e0a0'];
        for (var i = 0; i < 170; i++) {
            var a = Math.random() * Math.PI * 2, s = 5 + Math.random() * 11;
            bits.push({ x: x, y: y, vx: Math.cos(a) * s, vy: Math.sin(a) * s - 7, w: 6 + Math.random() * 7, h: 4 + Math.random() * 6, r: Math.random() * 6, vr: (Math.random() - .5) * .4, c: cols[i % cols.length], life: 1 });
        }
        if (!raf) raf = requestAnimationFrame(fall);
    }
    function fall() {
        ctx.clearRect(0, 0, fx.width, fx.height);
        bits = bits.filter(function (b) { return b.life > 0 && b.y < fx.height + 30; });
        bits.forEach(function (b) {
            b.vy += .32; b.vx *= .992; b.x += b.vx; b.y += b.vy; b.r += b.vr; b.life -= .0045;
            ctx.save(); ctx.globalAlpha = Math.max(0, b.life); ctx.translate(b.x, b.y); ctx.rotate(b.r); ctx.fillStyle = b.c; ctx.fillRect(-b.w / 2, -b.h / 2, b.w, b.h); ctx.restore();
        });
        raf = bits.length ? requestAnimationFrame(fall) : null;
        if (!raf) ctx.clearRect(0, 0, fx.width, fx.height);
    }

    /* ================= reveal mode ================= */
    var lastPodTop = -1, steps = [], at = 0, autoTimer = null, revealing = false;
    var ctl = { next: document.getElementById('ctlNext'), auto: document.getElementById('ctlAuto'), count: document.getElementById('ctlCount') };

    function prepare() {
        steps = []; at = 0; lastPodTop = -1;
        Array.prototype.forEach.call(stages.querySelectorAll('.q'), function (q) {
            q.classList.remove('podon');
            var rows = Array.prototype.slice.call(q.querySelectorAll('.row'));
            var pods = Array.prototype.slice.call(q.querySelectorAll('.pod')).sort(function (a, b) { return +b.getAttribute('data-pos') - +a.getAttribute('data-pos'); });
            var podIds = pods.map(function (p) { return p.getAttribute('data-id'); });
            rows.forEach(function (r) { r.classList.add('rh'); var bi = r.querySelector('.bar i'); r.setAttribute('data-w', bi.style.width); bi.style.width = '0'; var b = r.querySelector('.fig b'); r.setAttribute('data-final', b.getAttribute('data-v')); b.setAttribute('data-v', 0); b.textContent = '0'; });
            Array.prototype.forEach.call(q.querySelectorAll('.cut'), function (c) { c.classList.add('rh'); });
            pods.forEach(function (p) { p.classList.add('rh'); var w = p.querySelector('.w'); p.setAttribute('data-final', w.getAttribute('data-v')); w.setAttribute('data-v', 0); w.textContent = '0'; });
            steps.push({ q: q, kind: 'title' });
            rows.slice().reverse().filter(function (r) { return podIds.indexOf(r.getAttribute('data-id')) < 0; }).forEach(function (r) { steps.push({ q: q, kind: 'row', row: r }); });
            pods.forEach(function (p, i) { steps.push({ q: q, kind: 'pod', pod: p, row: q.querySelector('.row[data-id="' + p.getAttribute('data-id') + '"]'), last: i === pods.length - 1 }); });
        });
        paintCount();
    }
    function showRow(r, ms) {
        if (!r) return;
        r.classList.remove('rh'); r.classList.add('pop');
        var bi = r.querySelector('.bar i'); bi.style.width = r.getAttribute('data-w');
        count(r.querySelector('.fig b'), r.getAttribute('data-final'), ms || 1500);
        var prev = r.previousElementSibling; // the cut-off line sits just above the first non-winner
        var next = r.nextElementSibling; if (next && next.classList.contains('cut')) next.classList.remove('rh');
        if (prev && prev.classList.contains('cut')) { /* shown with the winner above it */ }
    }
    function paintCount() { ctl.count.textContent = at + ' / ' + steps.length; ctl.next.textContent = at >= steps.length ? 'Done' : 'Next →'; }
    // One step of the reveal. Returns how long (ms) the auto-play should wait before the next one.
    function next() {
        if (!revealing || at >= steps.length) { stopAuto(); return 0; }
        var s = steps[at++], delay = 1700;
        var target = s.kind === 'title' ? s.q : (s.pod || s.row);
        if (s.kind === 'pod') s.q.classList.add('podon');
        // rows just appear below the previous one; only move the page when the new item is off screen
        // no scrolling during the reveal: rows stack up where you are, the winners' tree opens at the end
        if (s.kind === 'title') delay = 2600;
        if (s.kind === 'row') {
            showRow(s.row, 1500);
        }
        if (s.kind === 'pod') {
            // a winner: his number climbs from 0 slowly, his row fills in the list underneath
            s.pod.classList.remove('rh'); s.pod.classList.add('pop');
            count(s.pod.querySelector('.w'), s.pod.getAttribute('data-final'), 1600);
            showRow(s.row, 1600);
            delay = s.last ? 3600 : 2400;
            if (s.last) { s.q.classList.add('done'); setTimeout(function () { s.q.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'start' }); }, 150); setTimeout(function () { var b = s.pod.getBoundingClientRect(); confetti(b.left + b.width / 2, b.top + b.height / 3); }, 1500); }
        }
        paintCount();
        if (at >= steps.length) { document.body.classList.add('finished'); }
        return delay;
    }
    var autoOn = false, autoT = null;
    function stopAuto() { autoOn = false; clearTimeout(autoT); autoT = null; ctl.auto.classList.remove('on'); ctl.auto.textContent = 'Auto'; }
    function autoStep() {
        if (!autoOn) return;
        var d = next();
        if (!autoOn) return;
        if (at >= steps.length) { stopAuto(); return; }
        autoT = setTimeout(autoStep, d);
    }
    function toggleAuto() {
        if (autoOn) { stopAuto(); return; }
        autoOn = true; ctl.auto.classList.add('on'); ctl.auto.textContent = 'Auto \u2022 on';
        autoStep();
    }
    function startReveal() {
        if (!chosen().length) return;
        live = false; clearInterval(timer);
        refresh().then(function () {
            revealing = true; document.body.classList.add('reveal');
            liveBtn.classList.add('paused'); liveText.textContent = 'REVEAL';
            document.getElementById('revealBtn').style.display = 'none';
            window.scrollTo({ top: 0 });
            prepare();
            setTimeout(toggleAuto, 900);   // starts playing by itself; pause / Next stay available
        });
    }
    document.getElementById('revealBtn').addEventListener('click', startReveal);
    ctl.next.addEventListener('click', next);
    ctl.auto.addEventListener('click', toggleAuto);
    document.getElementById('ctlReset').addEventListener('click', function () { location.reload(); });
    document.getElementById('ctlExit').addEventListener('click', function () { location.reload(); });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') { if (spot.classList.contains('on')) spot.classList.remove('on'); else if (revealing) location.reload(); return; }
        if (!revealing || /INPUT|TEXTAREA/.test((e.target || {}).tagName || '')) return;
        if (e.key === ' ' || e.key === 'ArrowRight' || e.key === 'Enter') { e.preventDefault(); next(); }
    });

    render(cfg.initial);
    schedule();
})();
</script>
</body>
</html>
