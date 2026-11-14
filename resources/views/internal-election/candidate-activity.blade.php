@extends('layouts.cms')

@section('header_resources')
<meta http-equiv="refresh" content="60">
@endsection

@section('content')
<div class="box box-default">
    <div class="box-header with-border">
        <h3 class="box-title">{{ $election->title }} — نشاط المرشحين / Candidate Activity</h3>
    </div>
    <div class="box-body">
        <p class="text-muted">
            يبيّن هذا الجدول فقط إن كان المرشح قد حصل على أصوات أم لا، دون أي أعداد أو ترتيب. تتحدّث الصفحة تلقائياً كل دقيقة.
            <br>This page only shows whether a candidate has received any votes yet — no counts, totals, ranking or times.
        </p>

        @forelse($sections as $section)
            <div class="box box-default">
                <div class="box-header with-border">
                    <h3 class="box-title">{{ $section['district'] }}</h3>
                    @if($section['voting_disabled'])
                        <span class="label label-default">التصويت غير مفعّل في هذا القضاء</span>
                    @endif
                </div>
                <div class="box-body">
                    <table class="table table-bordered table-striped" style="margin-bottom:0;">
                        <thead>
                            <tr>
                                <th>المرشح</th>
                                <th style="width: 260px;">الحالة</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($section['candidates'] as $candidate)
                                <tr>
                                    <td>{{ $candidate['name'] }}</td>
                                    <td>
                                        @if($section['voting_disabled'])
                                            <span class="text-muted">—</span>
                                        @elseif($candidate['has_votes'])
                                            <span class="label label-success" style="font-size:13px;">يتلقّى أصواتاً — Receiving votes</span>
                                        @else
                                            <span class="label label-default" style="font-size:13px;">لا أصوات بعد — No votes yet</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @empty
            <div class="alert alert-warning" style="margin:0;">No candidates have been added to this election yet.</div>
        @endforelse
    </div>
</div>
@endsection
