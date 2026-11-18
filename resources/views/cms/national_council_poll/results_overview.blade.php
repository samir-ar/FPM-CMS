@extends($layout)

@section('content')
<style>
    /* LIVE RESULTS STAGE. A dark broadcast-style canvas (easy on the eyes on a projector or a long watch), one warm
       accent for the winners, slate for everyone else. Scoped with .rs- so nothing else in the CMS is touched. */
    .rs { --bg:#0b0f14; --panel:#121820; --panel-2:#171e27; --line:rgba(255,255,255,.08); --ink:#f1f4f7; --ink-2:#9aa6b2; --ink-3:#66717c;
          --accent:#ff6a2b; --accent-2:#ffb18c; --slate:#3b4755; --good:#38c98a; --bad:#ff6b6b;
          background:var(--bg); color:var(--ink); border-radius:14px; padding:26px 28px 34px; min-height:86vh; position:relative;
          font-variant-numeric:tabular-nums; letter-spacing:.005em; }
    .rs * { box-sizing:border-box; }
    .rs:fullscreen { border-radius:0; overflow:auto; padding:34px 5vw; }
    .rs a { color:var(--ink-2); }

    /* top bar */
    .rs-top { display:flex; flex-wrap:wrap; align-items:center; gap:14px 18px; margin-bottom:6px; }
    .rs-brand { font-size:12px; font-weight:700; letter-spacing:.22em; text-transform:uppercase; color:var(--ink-2); }
    .rs-live { display:inline-flex; align-items:center; gap:8px; padding:5px 12px; border-radius:999px; background:var(--panel-2); border:1px solid var(--line); font-size:12px; font-weight:700; letter-spacing:.14em; color:var(--ink-2); cursor:pointer; user-select:none; }
    .rs-live i { width:8px; height:8px; border-radius:50%; background:var(--ink-3); }
    .rs-live.on { color:#fff; }
    .rs-live.on i { background:var(--bad); box-shadow:0 0 0 0 rgba(255,107,107,.6); animation:rs-pulse 1.8s infinite; }
    .rs-live.err i { background:#e5a50a; animation:none; }
    @keyframes rs-pulse { 70% { box-shadow:0 0 0 9px rgba(255,107,107,0); } 100% { box-shadow:0 0 0 0 rgba(255,107,107,0); } }
    .rs-at { font-size:12px; color:var(--ink-3); }
    .rs-spacer { flex:1; }
    .rs-btn { background:var(--panel-2); border:1px solid var(--line); color:var(--ink); height:32px; padding:0 14px; border-radius:8px; font-size:12.5px; font-weight:600; cursor:pointer; }
    .rs-btn:hover { background:#1e2733; }
    .rs-topn { display:inline-flex; align-items:center; gap:8px; font-size:12.5px; color:var(--ink-2); }
    .rs-topn input { width:56px; height:32px; background:var(--panel-2); border:1px solid var(--line); color:var(--ink); border-radius:8px; text-align:center; }

    /* poll selector */
    .rs-chips { display:flex; flex-wrap:wrap; gap:8px; margin:16px 0 26px; }
    .rs-chip { position:relative; margin:0; cursor:pointer; }
    .rs-chip input { position:absolute; opacity:0; inset:0; cursor:pointer; }
    .rs-chip span { display:inline-block; padding:8px 16px; border-radius:999px; background:var(--panel); border:1px solid var(--line); color:var(--ink-2); font-size:13px; font-weight:600; transition:all .15s; }
    .rs-chip small { margin-inline-start:7px; font-weight:500; color:var(--ink-3); }
    .rs-chip:hover span { border-color:rgba(255,255,255,.2); color:var(--ink); }
    .rs-chip input:checked + span { background:var(--accent); border-color:var(--accent); color:#fff; }
    .rs-chip input:checked + span small { color:rgba(255,255,255,.8); }
    .rs-chip input:focus-visible + span { outline:2px solid #fff; outline-offset:2px; }

    /* one poll */
    .rs-poll { margin-bottom:46px; animation:rs-in .5s ease both; }
    @keyframes rs-in { from { opacity:0; transform:translateY(10px); } to { opacity:1; transform:none; } }
    .rs-poll-title { font-size:30px; font-weight:700; line-height:1.2; letter-spacing:-.01em; margin:0 0 4px; }
    .rs-poll-meta { color:var(--ink-3); font-size:13px; margin-bottom:22px; }
    .rs-poll-meta b { color:var(--ink-2); font-weight:600; }

    .rs-hero { display:grid; grid-template-columns:220px repeat(3,1fr); gap:0; background:var(--panel); border:1px solid var(--line); border-radius:14px; overflow:hidden; margin-bottom:26px; }
    .rs-ringbox { display:flex; align-items:center; justify-content:center; padding:20px; position:relative; border-inline-end:1px solid var(--line); }
    .rs-ring { width:150px; height:150px; transform:rotate(-90deg); }
    .rs-ring circle { fill:none; stroke-width:10; }
    .rs-ring .bg { stroke:var(--panel-2); }
    .rs-ring .fg { stroke:var(--accent); stroke-linecap:round; transition:stroke-dashoffset 1.1s cubic-bezier(.2,.8,.2,1); }
    .rs-ring-label { position:absolute; text-align:center; }
    .rs-ring-label b { display:block; font-size:38px; font-weight:700; line-height:1; letter-spacing:-.02em; }
    .rs-ring-label span { font-size:11px; letter-spacing:.16em; text-transform:uppercase; color:var(--ink-3); }
    .rs-stat { padding:24px 26px; border-inline-end:1px solid var(--line); display:flex; flex-direction:column; justify-content:center; }
    .rs-stat:last-child { border-inline-end:0; }
    .rs-stat .l { font-size:11px; letter-spacing:.16em; text-transform:uppercase; color:var(--ink-3); margin-bottom:8px; }
    .rs-stat .n { font-size:44px; font-weight:700; line-height:1; letter-spacing:-.02em; }
    .rs-stat .s { margin-top:8px; font-size:12.5px; color:var(--ink-3); }

    /* question */
    .rs-q { margin-top:34px; }
    .rs-q-title { font-size:20px; font-weight:700; margin:0 0 4px; }
    .rs-q-sub { font-size:12.5px; color:var(--ink-3); margin-bottom:22px; }
    .rs-tie { display:inline-block; margin-inline-start:12px; padding:3px 10px; border-radius:6px; background:rgba(255,106,43,.14); color:var(--accent-2); font-size:12px; font-weight:600; }

    /* podium */
    .rs-podium { display:flex; justify-content:center; align-items:flex-end; gap:26px; padding:12px 0 30px; flex-wrap:wrap; }
    .rs-pod { text-align:center; width:210px; animation:rs-in .6s ease both; }
    .rs-pod .av { margin:0 auto 14px; position:relative; }
    .rs-pod.p1 .av { width:132px; height:132px; }
    .rs-pod.p2 .av, .rs-pod.p3 .av { width:98px; height:98px; }
    .rs-pod .badge { position:absolute; inset-inline-end:-4px; bottom:-4px; width:34px; height:34px; border-radius:50%; background:var(--bg); border:2px solid var(--slate); display:flex; align-items:center; justify-content:center; font-weight:700; font-size:15px; }
    .rs-pod.win .badge, .rs-pod.p1 .badge { border-color:var(--accent); color:var(--accent-2); }
    .rs-pod .nm { font-size:16px; font-weight:600; line-height:1.3; margin-bottom:6px; unicode-bidi:plaintext; }
    .rs-pod.p1 .nm { font-size:19px; }
    .rs-pod .w { font-size:34px; font-weight:700; line-height:1; letter-spacing:-.02em; }
    .rs-pod.p1 .w { font-size:46px; color:var(--accent-2); }
    .rs-pod .pc { margin-top:6px; font-size:12.5px; color:var(--ink-3); }

    /* avatar */
    .rs-av { width:100%; height:100%; border-radius:50%; background:var(--panel-2); border:2px solid var(--slate); overflow:hidden; display:flex; align-items:center; justify-content:center; color:var(--ink-2); font-weight:700; }
    .rs-av img { width:100%; height:100%; object-fit:cover; display:block; }
    .win > .av .rs-av, .p1 .av .rs-av, .rs-row.win .rs-av, .rs-row.lead .rs-av { border-color:var(--accent); }

    /* ranked rows */
    .rs-rows { position:relative; }
    .rs-row { display:grid; grid-template-columns:34px 46px minmax(150px,280px) 1fr 190px; align-items:center; gap:16px; padding:10px 14px; border-radius:12px; margin-bottom:4px; background:transparent; transition:background .2s; }
    .rs-row:hover { background:var(--panel); }
    .rs-row.win, .rs-row.lead { background:var(--panel); }
    .rs-rank { font-size:15px; color:var(--ink-3); text-align:center; font-weight:600; }
    .rs-row.win .rs-rank, .rs-row.lead .rs-rank { color:var(--accent-2); }
    .rs-row .av { width:46px; height:46px; }
    .rs-nm { font-size:16px; font-weight:500; line-height:1.3; text-align:left; unicode-bidi:plaintext; }
    .rs-row.win .rs-nm, .rs-row.lead .rs-nm { font-weight:700; }
    .rs-track { height:14px; background:var(--panel-2); border-radius:7px; overflow:hidden; }
    .rs-fill { height:100%; width:0; background:var(--slate); border-radius:7px; transition:width 1.1s cubic-bezier(.2,.8,.2,1); min-width:3px; }
    .rs-row.win .rs-fill, .rs-row.lead .rs-fill { background:linear-gradient(90deg,#ff6a2b,#ff8f5c); }
    .rs-num { text-align:end; white-space:nowrap; color:var(--ink-3); font-size:13px; }
    .rs-num b { color:var(--ink); font-size:21px; font-weight:700; margin-inline-end:8px; }
    .rs-cut { display:flex; align-items:center; gap:14px; margin:10px 0 12px; color:var(--accent-2); font-size:11px; letter-spacing:.18em; text-transform:uppercase; font-weight:700; }
    .rs-cut:before, .rs-cut:after { content:""; flex:1; height:1px; background:linear-gradient(90deg,transparent,rgba(255,106,43,.6),transparent); }
    .rs-wait { text-align:center; color:var(--ink-3); padding:26px 0 10px; font-size:14px; }
    .rs-empty { text-align:center; color:var(--ink-3); padding:90px 0; font-size:15px; }
    .rs-empty b { display:block; color:var(--ink-2); font-size:20px; margin-bottom:8px; }

    @media (max-width:1000px) {
        .rs-hero { grid-template-columns:1fr 1fr; }
        .rs-ringbox { grid-column:1 / 3; border-inline-end:0; border-bottom:1px solid var(--line); }
        .rs-row { grid-template-columns:28px 40px 1fr 120px; }
        .rs-track { grid-column:2 / 5; grid-row:2; }
        .rs-pod { width:150px; }
    }
    @media (prefers-reduced-motion:reduce) { .rs-fill, .rs-ring .fg, .rs-poll, .rs-pod { transition:none; animation:none; } }
</style>

<div class="rs" id="rs">
    <div class="rs-top">
        <span class="rs-brand">National Council &middot; Results</span>
        <span class="rs-live on" id="rs-live" title="Click to pause / resume the live updates"><i></i><em style="font-style:normal">LIVE</em></span>
        <span class="rs-at" id="rs-at"></span>
        <span class="rs-spacer"></span>
        <label class="rs-topn">Mark the top <input type="number" id="rs-top" min="0" value="{{ $top ?: '' }}" placeholder="&ndash;"></label>
        <button type="button" class="rs-btn" id="rs-full">Full screen</button>
        <a class="rs-btn" style="display:inline-flex;align-items:center;text-decoration:none;" href="{{ route('admin.national-council-poll.index') }}">Back</a>
    </div>

    <div class="rs-chips" id="rs-chips">
        @forelse($polls as $p)
            <label class="rs-chip">
                <input type="checkbox" value="{{ $p->id }}" @if(in_array($p->id, $chosen)) checked @endif>
                <span><bdi>{{ $p->title }}</bdi><small>{{ $p->is_published ? 'published' : 'unpublished' }}@if($p->closes_at && \Carbon\Carbon::parse($p->closes_at)->isPast()), closed @endif</small></span>
            </label>
        @empty
            <span class="rs-at">There are no polls yet.</span>
        @endforelse
    </div>

    <div id="rs-stages"></div>
    <div class="rs-empty" id="rs-empty" style="display:none;"><b>Pick a poll above</b>Choose one or several polls to see their live results here.</div>
</div>

<script>
(function () {
    var cfg = {
        initial: {!! json_encode($initial, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!},
        dataUrl: {!! json_encode($dataUrl) !!},
        every: 5000
    };
    var root = document.getElementById('rs'), stages = document.getElementById('rs-stages'), emptyBox = document.getElementById('rs-empty');
    var liveBtn = document.getElementById('rs-live'), atEl = document.getElementById('rs-at'), topInput = document.getElementById('rs-top');
    var live = true, timer = null, reduce = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;

    function el(tag, cls, text) { var e = document.createElement(tag); if (cls) e.className = cls; if (text != null) e.textContent = text; return e; }
    function fmt(n) { return Number(n).toLocaleString('en-US'); }

    // numbers glide to their new value instead of jumping
    function count(node, to) {
        to = Number(to) || 0;
        var from = Number(node.getAttribute('data-v')); if (isNaN(from)) from = 0;
        node.setAttribute('data-v', to);
        if (reduce || from === to) { node.textContent = fmt(to); return; }
        var t0 = null;
        function step(ts) {
            if (t0 === null) t0 = ts;
            var k = Math.min(1, (ts - t0) / 900), e = 1 - Math.pow(1 - k, 3);
            node.textContent = fmt(Math.round(from + (to - from) * e));
            if (k < 1) requestAnimationFrame(step);
        }
        requestAnimationFrame(step);
    }

    function avatar(name, photo) {
        var wrap = el('div', 'rs-av');
        var initial = (name || '?').trim().charAt(0);
        if (photo) {
            var img = new Image();
            img.alt = ''; img.loading = 'lazy';
            img.onerror = function () { wrap.textContent = initial; img.remove(); };
            img.src = photo;
            wrap.appendChild(img);
        } else { wrap.textContent = initial; }
        return wrap;
    }

    function isWin(row, top) { return top ? (row.rank <= top && row.weight > 0) : false; }
    function isLead(row, top, cast) { return !top && cast > 0 && row.rank === 1; }

    /* ---------- podium (top three) ---------- */
    function renderPodium(box, q, top) {
        var rows = q.rows.filter(function (r) { return r.weight > 0; }).slice(0, 3);
        var sig = rows.map(function (r) { return r.id + ':' + r.weight; }).join('|') + '#' + top;
        if (box.getAttribute('data-sig') === sig) return;
        box.setAttribute('data-sig', sig);
        box.textContent = '';
        // visual order: 2nd, 1st, 3rd
        [1, 0, 2].forEach(function (i) {
            var r = rows[i]; if (!r) return;
            var pod = el('div', 'rs-pod p' + (i + 1) + (isWin(r, top) ? ' win' : ''));
            var av = el('div', 'av'); av.appendChild(avatar(r.name, r.photo));
            av.appendChild(el('div', 'badge', r.rank));
            pod.appendChild(av);
            pod.appendChild(el('div', 'nm', r.name));
            var w = el('div', 'w'); count(w, r.weight); pod.appendChild(w);
            pod.appendChild(el('div', 'pc', r.votes + (r.votes === 1 ? ' vote' : ' votes') + ' · ' + r.pct + '%'));
            box.appendChild(pod);
        });
    }

    /* ---------- ranked list, rows keyed by option id, re-ranking animated (FLIP) ---------- */
    function renderRows(list, q, top) {
        var max = 1; q.rows.forEach(function (r) { if (r.weight > max) max = r.weight; });
        var existing = {}; Array.prototype.forEach.call(list.querySelectorAll('.rs-row'), function (n) { existing[n.getAttribute('data-id')] = n; });
        var before = {}; Object.keys(existing).forEach(function (k) { before[k] = existing[k].getBoundingClientRect().top; });
        Array.prototype.forEach.call(list.querySelectorAll('.rs-cut'), function (n) { n.remove(); });

        var seen = {};
        q.rows.forEach(function (r, idx) {
            var node = existing[r.id];
            if (!node) {
                node = el('div', 'rs-row'); node.setAttribute('data-id', r.id);
                node.appendChild(el('div', 'rs-rank'));
                var av = el('div', 'av'); av.appendChild(avatar(r.name, r.photo)); node.appendChild(av);
                node.appendChild(el('div', 'rs-nm', r.name));
                var tr = el('div', 'rs-track'); tr.appendChild(el('div', 'rs-fill')); node.appendChild(tr);
                var num = el('div', 'rs-num'); num.appendChild(el('b')); num.appendChild(el('span')); node.appendChild(num);
            }
            seen[r.id] = true;
            node.className = 'rs-row' + (isWin(r, top) ? ' win' : '') + (isLead(r, top, q.cast_weight) ? ' lead' : '');
            node.children[0].textContent = r.rank;
            node.children[2].textContent = r.name;
            count(node.querySelector('b'), r.weight);
            node.querySelector('.rs-num span').textContent = r.votes + (r.votes === 1 ? ' vote' : ' votes') + ' · ' + r.pct + '%';
            node.title = r.name + ' — weight ' + r.weight + ', ' + r.votes + ' votes, ' + r.pct + '% of the weight';
            list.appendChild(node);                     // appending in rank order = sorting
            var fill = node.querySelector('.rs-fill');
            var w = (r.weight * 100 / max) + '%';
            if (!existing[r.id]) { requestAnimationFrame(function () { requestAnimationFrame(function () { fill.style.width = w; }); }); }
            else { fill.style.width = w; }

            // cut-off line after the last winner
            var next = q.rows[idx + 1];
            if (top && r.rank <= top && next && next.rank > top) { list.appendChild(el('div', 'rs-cut', 'Cut-off · top ' + top)); }
        });
        Object.keys(existing).forEach(function (k) { if (!seen[k]) existing[k].remove(); });

        // slide every row that changed place from where it was to where it is
        if (!reduce) {
            Object.keys(before).forEach(function (k) {
                var n = list.querySelector('.rs-row[data-id="' + k + '"]'); if (!n) return;
                var dy = before[k] - n.getBoundingClientRect().top;
                if (Math.abs(dy) > 1) {
                    n.style.transition = 'none'; n.style.transform = 'translateY(' + dy + 'px)';
                    requestAnimationFrame(function () { requestAnimationFrame(function () {
                        n.style.transition = 'transform .7s cubic-bezier(.2,.8,.2,1)'; n.style.transform = '';
                    }); });
                }
            });
        }
    }

    function renderQuestion(qWrap, q, top) {
        var node = qWrap.querySelector('[data-q="' + q.id + '"]');
        if (!node) {
            node = el('div', 'rs-q'); node.setAttribute('data-q', q.id);
            node.appendChild(el('h3', 'rs-q-title'));
            node.appendChild(el('div', 'rs-q-sub'));
            node.appendChild(el('div', 'rs-podium-box'));
            node.appendChild(el('div', 'rs-rows'));
            node.appendChild(el('div', 'rs-wait'));
            qWrap.appendChild(node);
        }
        node.querySelector('.rs-q-title').textContent = q.text;
        var sub = node.querySelector('.rs-q-sub');
        sub.textContent = 'Weight cast ' + fmt(q.cast_weight);
        if (q.tie_at_cutoff) { sub.appendChild(el('span', 'rs-tie', 'Tie at the cut-off – a decision is needed')); }

        var podium = node.querySelector('.rs-podium-box');
        podium.className = 'rs-podium-box';
        var podiumInner = podium.querySelector('.rs-podium');
        if (!podiumInner) { podiumInner = el('div', 'rs-podium'); podium.appendChild(podiumInner); }
        if (q.cast_weight > 0) { renderPodium(podiumInner, q, top); podiumInner.style.display = ''; } else { podiumInner.style.display = 'none'; }

        renderRows(node.querySelector('.rs-rows'), q, top);
        node.querySelector('.rs-wait').textContent = q.cast_weight > 0 ? '' : 'Waiting for the first vote…';
    }

    function renderPoll(p, top) {
        var sec = stages.querySelector('[data-poll="' + p.id + '"]');
        if (!sec) {
            sec = el('section', 'rs-poll'); sec.setAttribute('data-poll', p.id);
            sec.appendChild(el('h2', 'rs-poll-title'));
            sec.appendChild(el('div', 'rs-poll-meta'));

            var hero = el('div', 'rs-hero');
            var rb = el('div', 'rs-ringbox');
            rb.innerHTML = '<svg class="rs-ring" viewBox="0 0 120 120"><circle class="bg" cx="60" cy="60" r="52"/><circle class="fg" cx="60" cy="60" r="52" stroke-dasharray="326.7" stroke-dashoffset="326.7"/></svg>' +
                           '<div class="rs-ring-label"><b data-f="turnout">0</b><span>Turnout %</span></div>';
            hero.appendChild(rb);
            [['voted', 'Voted'], ['weight', 'Weight that voted'], ['qs', 'Questions']].forEach(function (s) {
                var st = el('div', 'rs-stat'); st.appendChild(el('div', 'l', s[1])); st.appendChild(el('div', 'n')); st.lastChild.setAttribute('data-f', s[0]); st.appendChild(el('div', 's')); st.lastChild.setAttribute('data-s', s[0]); hero.appendChild(st);
            });
            sec.appendChild(hero);
            sec.appendChild(el('div', 'rs-qs'));
            stages.appendChild(sec);
        }
        sec.querySelector('.rs-poll-title').textContent = p.title;
        var meta = sec.querySelector('.rs-poll-meta');
        meta.textContent = (p.published ? 'Published' : 'Not published') + (p.closes_at ? ' · ' + (p.closed ? 'closed ' : 'closes ') + p.closes_at : '');

        sec.querySelector('.rs-ring .fg').style.strokeDashoffset = 326.7 * (1 - Math.min(100, p.turnout) / 100);
        var tn = sec.querySelector('[data-f="turnout"]');
        var from = Number(tn.getAttribute('data-v')) || 0; tn.setAttribute('data-v', p.turnout);
        tn.textContent = String(p.turnout).replace(/\.0$/, '');
        count(sec.querySelector('[data-f="voted"]'), p.voters);
        count(sec.querySelector('[data-f="weight"]'), p.voted_weight);
        count(sec.querySelector('[data-f="qs"]'), p.questions.length);
        sec.querySelector('[data-s="voted"]').textContent = 'of ' + fmt(p.electors) + ' allowed';
        sec.querySelector('[data-s="weight"]').textContent = 'of ' + fmt(p.total_weight);
        sec.querySelector('[data-s="qs"]').textContent = '';

        var qWrap = sec.querySelector('.rs-qs');
        p.questions.forEach(function (q) { renderQuestion(qWrap, q, top); });
        var keep = p.questions.map(function (q) { return String(q.id); });
        Array.prototype.forEach.call(qWrap.querySelectorAll('[data-q]'), function (n) { if (keep.indexOf(n.getAttribute('data-q')) < 0) n.remove(); });
        if (!p.questions.length && !qWrap.firstChild) { qWrap.appendChild(el('div', 'rs-wait', 'This poll has no questions yet.')); }
    }

    function render(data) {
        var ids = data.polls.map(function (p) { return String(p.id); });
        Array.prototype.forEach.call(stages.querySelectorAll('[data-poll]'), function (n) { if (ids.indexOf(n.getAttribute('data-poll')) < 0) n.remove(); });
        data.polls.forEach(function (p) { renderPoll(p, data.top); });
        // keep the sections in the order of the data
        data.polls.forEach(function (p) { stages.appendChild(stages.querySelector('[data-poll="' + p.id + '"]')); });
        emptyBox.style.display = data.polls.length ? 'none' : 'block';
        atEl.textContent = 'Updated ' + data.at;
    }

    function chosen() { return Array.prototype.map.call(document.querySelectorAll('#rs-chips input:checked'), function (c) { return c.value; }); }
    function query() { var q = chosen().map(function (v) { return 'polls[]=' + encodeURIComponent(v); }); var t = parseInt(topInput.value, 10); if (t > 0) q.push('top=' + t); return q.join('&'); }

    function refresh() {
        var ids = chosen();
        if (!ids.length) { render({ polls: [], top: 0, at: new Date().toLocaleTimeString('en-GB') }); return Promise.resolve(); }
        return fetch(cfg.dataUrl + '?' + query(), { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(function (r) { if (!r.ok) throw new Error(r.status); return r.json(); })
            .then(function (d) { liveBtn.classList.remove('err'); render(d); })
            .catch(function () { liveBtn.classList.add('err'); atEl.textContent = 'Connection problem – retrying…'; });
    }

    function schedule() {
        clearInterval(timer);
        if (live) timer = setInterval(function () { if (!document.hidden) refresh(); }, cfg.every);
    }

    document.getElementById('rs-chips').addEventListener('change', function () {
        history.replaceState(null, '', location.pathname + (query() ? '?' + query() : ''));
        refresh();
    });
    var topTimer; topInput.addEventListener('input', function () { clearTimeout(topTimer); topTimer = setTimeout(function () { history.replaceState(null, '', location.pathname + (query() ? '?' + query() : '')); refresh(); }, 350); });
    liveBtn.addEventListener('click', function () { live = !live; liveBtn.classList.toggle('on', live); liveBtn.querySelector('em').textContent = live ? 'LIVE' : 'PAUSED'; schedule(); if (live) refresh(); });
    function toggleFull() { if (document.fullscreenElement) document.exitFullscreen(); else if (root.requestFullscreen) root.requestFullscreen(); }
    document.getElementById('rs-full').addEventListener('click', toggleFull);
    document.addEventListener('keydown', function (e) { if ((e.key === 'f' || e.key === 'F') && !/INPUT|TEXTAREA/.test((e.target || {}).tagName || '')) toggleFull(); });

    render(cfg.initial);
    schedule();
})();
</script>
@endsection
