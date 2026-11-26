@extends('layouts.cms')

@section('header_resources')
    <style>
        .screen-col h3 { margin: 0 0 14px; font-size: 20px; font-weight: 600; }
        .screen-col h3 .fa { color: #3c8dbc; margin-right: 8px; }
        .screen-tile { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 18px 20px; margin-bottom: 12px;
                       background: #004e7c; color: #fff; border-radius: 10px; border-left: 6px solid #f48d24; }
        .screen-tile:hover, .screen-tile:focus { color: #fff; background: #00639d; text-decoration: none; }
        .screen-tile .t { font-size: 20px; font-weight: 700; line-height: 1.35; text-align: left; margin-bottom: 4px; }
        .screen-tile .d { color: #b3c9d9; font-size: 13px; }
        .screen-tile .go { font-size: 26px; color: #f48d24; }
        .screen-state { display: inline-block; font-size: 11px; font-weight: 700; letter-spacing: .08em; border-radius: 10px; padding: 2px 9px; margin-right: 6px; background: rgba(255,255,255,.14); }
        .screen-state.live { background: #1f9d6b; }
        .screen-empty { padding: 18px 20px; border: 2px dashed #d6dde3; border-radius: 10px; color: #778; margin-bottom: 12px; }
    </style>
@endsection

@section('content')
    <div class="row">
        @foreach([['Live attendance', 'fa-users', $meetings, 'No meeting yet.'], ['Live voting', 'fa-check-square-o', $polls, 'No poll yet.']] as [$heading, $icon, $items, $empty])
            <div class="col-md-4 screen-col">
                <h3><i class="fa {{ $icon }}"></i>{{ $heading }}</h3>
                @forelse($items as $item)
                    <a class="screen-tile" href="{{ $item['url'] }}">
                        <span>
                            <span class="t" dir="auto" style="display:block">{{ $item['title'] }}</span>
                            <span class="screen-state {{ $item['state'] === 'LIVE' ? 'live' : '' }}">{{ $item['state'] }}</span>
                            <span class="d">{{ $item['detail'] }}</span>
                        </span>
                        <span class="go"><i class="fa fa-play-circle"></i></span>
                    </a>
                @empty
                    <div class="screen-empty">{{ $empty }}</div>
                @endforelse
            </div>
        @endforeach
        <div class="col-md-4 screen-col">
            <h3><i class="fa fa-trophy"></i>Results</h3>
            <a class="screen-tile" href="{{ $resultsUrl }}">
                <span>
                    <span class="t" style="display:block">Results board</span>
                    <span class="d">{{ $resultsCount }} {{ $resultsCount === 1 ? 'poll' : 'polls' }} to show</span>
                </span>
                <span class="go"><i class="fa fa-play-circle"></i></span>
            </a>
        </div>
    </div>
@endsection
