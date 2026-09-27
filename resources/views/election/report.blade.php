<html>
    <head>
        <meta charset="utf-8">
        <link rel="stylesheet" href="/cms/css/report.css?v={{ filemtime(public_path('cms/css/report.css')) }}"/>
        <link rel="stylesheet" href="/landing/css/bootstrap.min.css"/>
    </head>

    <body dir="rtl">
        <header>
            <img src="/landing/img/tayyar.jpeg" class="page-logo">
            <div class="title-row">
                <span class="title-line"></span>
                <h1>{{ $election->title }}</h1>
                <span class="title-line"></span>
            </div>
            <p class="election-meta">نتائج الإنتخابات الداخلية لعام {{ $election->created_at->year }}</p>
        </header>

        <main>
            <div class="election-state-container">
                <form method="GET" class="district-filter no-print">
                    <details class="district-filter-dropdown">
                        <summary>تصفية حسب الدائرة</summary>
                        <div class="district-filter-panel">
                            @foreach($allStates as $state)
                                <label class="district-filter-option">
                                    <input type="checkbox" name="states[]" value="{{ $state->id }}"
                                        {{ in_array($state->id, $selectedStateIds) ? 'checked' : '' }}>
                                    {{ $state->name }}
                                </label>
                            @endforeach
                            <div class="district-filter-actions">
                                <button type="submit" class="district-filter-apply">تطبيق</button>
                                <a href="{{ url()->current() }}" class="district-filter-reset">إظهار الكل</a>
                            </div>
                        </div>
                    </details>
                </form>

                @forelse ($results as $row)
                    @php
                        $state = $row['state'];
                        $candidates = $row['candidates'];
                        $voters = $row['voters'];
                    @endphp

                    <div class="headline-container">
                        <h2>{{ $state->name }}</h2>
                    </div>

                    <div class="stats-container">
                        <div class='stats'>
                            <span class="stats-label">عدد الأصوات المدلى بها</span>
                            <span class="stats-data">{{ $voters }}</span>
                        </div>
                        <div class='stats'>
                            <span class="stats-label">عدد المرشحين</span>
                            <span class="stats-data">{{ $candidates->count() }}</span>
                        </div>
                    </div>

                    <table class="table" dir="rtl">
                        <colgroup>
                            <col style="width:8%">
                            <col style="width:12%">
                            <col style="width:42%">
                            <col style="width:19%">
                            <col style="width:19%">
                        </colgroup>
                        <thead>
                            <tr>
                                <th scope="col"><div class="cell cell-center">#</div></th>
                                <th scope="col"></th>
                                <th scope="col"><div class="cell">إسم المرشح</div></th>
                                <th scope="col"><div class="cell">عدد الأصوات</div></th>
                                <th scope="col"><div class="cell">النسبة</div></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($candidates as $i => $candidate)
                                @php
                                    // Top 3 by rank are the winners — only once voting has
                                    // actually started, so an untouched election doesn't
                                    // highlight the first 3 rows by list order alone.
                                    $isWinner = $voters > 0 && $i < 3;
                                    $pct = $voters > 0 ? round($candidate->votes_count * 100 / $voters) : 0;
                                @endphp
                                <tr class="{{ $isWinner ? 'winner-row' : '' }}">
                                    <th scope="row"><div class="cell cell-center">{{ $i + 1 }}</div></th>
                                    <td class="photo-cell">
                                        <img src="{{ $candidate->photo_url }}" class="candidate-photo" onerror="this.style.visibility='hidden'">
                                    </td>
                                    <td>
                                        <div class="cell">
                                            {{ $candidate->name }}
                                            @if($isWinner)
                                                <span class="winner-badge" title="من ضمن الفائزين الثلاثة الأوائل">&#127942;</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td><div class="cell">{{ $candidate->votes_count }}</div></td>
                                    <td><div class="cell">{{ $pct }}%</div></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    </br>
                    </br>
                    </br>
                @empty
                    <p class="no-data">لا يوجد مرشحون بعد في أي دائرة انتخابية لهذه الإنتخابات.</p>
                @endforelse
            </div> <!--End of election-state-container-->
        </main>
    </body>
</html>
