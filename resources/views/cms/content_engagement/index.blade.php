@extends($layout)

@section('content')
<div class="row">
    <div class="col-md-6 col-sm-6">
        <div class="small-box bg-red">
            <div class="inner">
                <h3>{{ number_format($totalLikes) }}</h3>
                <p>Total Likes (all time)</p>
            </div>
            <div class="icon"><i class="fa fa-heart"></i></div>
        </div>
    </div>
    <div class="col-md-6 col-sm-6">
        <div class="small-box bg-aqua">
            <div class="inner">
                <h3>{{ number_format($totalShares) }}</h3>
                <p>Total Shares (all time)</p>
            </div>
            <div class="icon"><i class="fa fa-share-alt"></i></div>
        </div>
    </div>
</div>

<div class="box box-default">
    <div class="box-header with-border">
        <h3 class="box-title">Daily Likes — Last 30 Days</h3>
    </div>
    <div class="box-body">
        <div id="likes-trend-chart" style="height: 260px;"></div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="box box-default">
            <div class="box-header with-border">
                <h3 class="box-title">Likes by Month — Last 12 Months</h3>
            </div>
            <div class="box-body">
                <div id="likes-by-month-chart" style="height: 260px;"></div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="box box-default">
            <div class="box-header with-border">
                <h3 class="box-title">Shares by Article Post Month — Last 12 Months</h3>
            </div>
            <div class="box-body">
                <div id="shares-by-month-chart" style="height: 260px;"></div>
                <p class="text-muted" style="margin-top:8px;">
                    This is total shares of articles <strong>posted</strong> in each month, not when the
                    shares themselves happened — share actions have no timestamp in the current data.
                </p>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="box box-default">
            <div class="box-header with-border">
                <h3 class="box-title">Top 5 Districts by Likes — Last 12 Months</h3>
            </div>
            <div class="box-body">
                @if(count($districtLikesSeries) > 0)
                    <div id="district-likes-chart" style="height: 300px;"></div>
                @else
                    <p class="text-muted text-center">No likes with a known district yet.</p>
                @endif
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="box box-default">
            <div class="box-header with-border">
                <h3 class="box-title">Top 5 Districts by Shares — Last 12 Months</h3>
            </div>
            <div class="box-body">
                @if(count($districtSharesSeries) > 0)
                    <div id="district-shares-chart" style="height: 300px;"></div>
                @else
                    <p class="text-muted text-center">No shares logged yet — tracking only started {{ \Carbon\Carbon::parse($shareTrackingStartedAt)->format('M j, Y') }}, no retroactive history exists.</p>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="box box-default">
    <div class="box-body">
        <form method="GET" class="form-inline">
            <div class="form-group" style="margin-right:10px;">
                <label style="margin-right:6px;">From</label>
                <input type="date" name="date_from" class="form-control" value="{{ $dateFrom }}">
            </div>
            <div class="form-group" style="margin-right:10px;">
                <label style="margin-right:6px;">To</label>
                <input type="date" name="date_to" class="form-control" value="{{ $dateTo }}">
            </div>
            <button type="submit" class="btn btn-primary">Apply</button>
            <a href="{{ route('admin.content-engagement.index') }}" class="btn btn-default">Clear</a>
        </form>
        <p class="text-muted" style="margin-top:8px; margin-bottom:0;">
            Applies to the four Top-20 lists below only (not the charts above). Leave blank for all time.
            @if($hasDateFilter)
                Note: with a date filter active, "shares" below come from the new per-user share log,
                which only has data from {{ \Carbon\Carbon::parse($shareTrackingStartedAt)->format('M j, Y') }} onward.
            @endif
        </p>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="box box-default">
            <div class="box-header with-border">
                <h3 class="box-title">Top 20 Most Liked Articles</h3>
            </div>
            <div class="box-body table-responsive" style="padding:0;">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Posted</th>
                            <th class="text-right">Likes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($topLiked as $article)
                        <tr>
                            <td>
                                <a href="{{ route('admin.content-engagement.show', $article->id) }}">
                                    {{ \Illuminate\Support\Str::limit(strip_tags($article->title), 60) }}
                                </a>
                            </td>
                            <td>{{ optional($article->created_at)->format('Y-m-d') }}</td>
                            <td class="text-right"><strong>{{ number_format($article->users_count) }}</strong></td>
                        </tr>
                        @empty
                        <tr><td colspan="3" class="text-center text-muted">No likes yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="box box-default">
            <div class="box-header with-border">
                <h3 class="box-title">Top 20 Most Shared Articles</h3>
            </div>
            <div class="box-body table-responsive" style="padding:0;">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Posted</th>
                            <th class="text-right">Shares</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($topShared as $article)
                        <tr>
                            <td>{{ \Illuminate\Support\Str::limit(strip_tags($article->title), 60) }}</td>
                            <td>{{ optional($article->created_at)->format('Y-m-d') }}</td>
                            <td class="text-right"><strong>{{ number_format($article->shares) }}</strong></td>
                        </tr>
                        @empty
                        <tr><td colspan="3" class="text-center text-muted">No shares yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                <p class="text-muted" style="padding:10px;">
                    This article-level total has no per-user or per-time tracking behind it (it's just a
                    running counter), so no trend or click-through breakdown is possible for it. The
                    per-user leaderboard below is a separate, newly-added log that only counts shares
                    made from now on.
                </p>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="box box-default">
            <div class="box-header with-border">
                <h3 class="box-title">Top 20 Users by Likes</h3>
            </div>
            <div class="box-body table-responsive" style="padding:0;">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Member ID</th>
                            <th>District</th>
                            <th>Town</th>
                            <th class="text-right">Likes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($topLikingUsers as $u)
                        <tr>
                            <td>{{ $u->name }}</td>
                            <td>{{ $u->member_id }}</td>
                            <td>{{ $u->district }}</td>
                            <td>{{ $u->town }}</td>
                            <td class="text-right"><strong>{{ number_format($u->like_count) }}</strong></td>
                        </tr>
                        @empty
                        <tr><td colspan="5" class="text-center text-muted">No likes yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="box box-default">
            <div class="box-header with-border">
                <h3 class="box-title">Top 20 Users by Shares</h3>
            </div>
            <div class="box-body table-responsive" style="padding:0;">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Member ID</th>
                            <th>District</th>
                            <th>Town</th>
                            <th class="text-right">Shares</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($topSharingUsers as $u)
                        <tr>
                            <td>{{ $u->name }}</td>
                            <td>{{ $u->member_id }}</td>
                            <td>{{ $u->district }}</td>
                            <td>{{ $u->town }}</td>
                            <td class="text-right"><strong>{{ number_format($u->share_count) }}</strong></td>
                        </tr>
                        @empty
                        <tr><td colspan="5" class="text-center text-muted">No shares logged yet — tracking only started today, no retroactive history exists.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@section('footer_resources')
<script>
    $(function () {
        Morris.Line({
            element: 'likes-trend-chart',
            data: @json(collect($trendLabels)->map(fn($label, $i) => ['day' => $label, 'likes' => $trendValues[$i]])),
            xkey: 'day',
            ykeys: ['likes'],
            labels: ['Likes'],
            lineColors: ['#dd4b39'],
            resize: true,
            smooth: false,
        });

        Morris.Bar({
            element: 'likes-by-month-chart',
            data: @json(collect($monthlyLabels)->map(fn($label, $i) => ['month' => $label, 'likes' => $monthlyLikesValues[$i]])),
            xkey: 'month',
            ykeys: ['likes'],
            labels: ['Likes'],
            barColors: ['#dd4b39'],
            resize: true,
        });

        Morris.Bar({
            element: 'shares-by-month-chart',
            data: @json(collect($monthlyLabels)->map(fn($label, $i) => ['month' => $label, 'shares' => $monthlySharesValues[$i]])),
            xkey: 'month',
            ykeys: ['shares'],
            labels: ['Shares'],
            barColors: ['#00c0ef'],
            resize: true,
        });

        // Sorts the hover tooltip's rows highest-to-lowest instead of Morris'
        // default fixed series order.
        function sortedHoverCallback(index, options, content, row) {
            var items = options.ykeys.map(function (key, i) {
                return { name: options.labels[i], value: row[key] };
            });
            items.sort(function (a, b) { return b.value - a.value; });
            var html = '<div class="morris-hover-row-label">' + row[options.xkey] + '</div>';
            items.forEach(function (item) {
                html += '<div class="morris-hover-point">' + item.name + ': ' + item.value + '</div>';
            });
            return html;
        }

        @if(count($districtLikesSeries) > 0)
        Morris.Bar({
            element: 'district-likes-chart',
            data: @json($districtLikesChartData),
            xkey: 'month',
            ykeys: @json(array_keys($districtLikesSeries)),
            labels: @json(array_keys($districtLikesSeries)),
            resize: true,
            hoverCallback: sortedHoverCallback,
        });
        @endif

        @if(count($districtSharesSeries) > 0)
        Morris.Bar({
            element: 'district-shares-chart',
            data: @json($districtSharesChartData),
            xkey: 'month',
            ykeys: @json(array_keys($districtSharesSeries)),
            labels: @json(array_keys($districtSharesSeries)),
            resize: true,
            hoverCallback: sortedHoverCallback,
        });
        @endif
    });
</script>
@endsection
