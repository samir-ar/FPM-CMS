@extends('layouts.cms')

@section('content')
<div class="box box-default">
    <div class="box-header with-border">
        <h3 class="box-title">{{ $election->title }} — Votes Report</h3>
        <div class="box-tools pull-right">
            <a href="{{ route('admin.internal-election.votes-report.export', $election->id) }}" class="btn btn-success btn-sm">
                <i class="fa fa-file-excel-o"></i> Export to Excel
            </a>
        </div>
    </div>
    <div class="box-body">
        @if($votes->isEmpty())
            <div class="alert alert-warning">No votes recorded yet for this election.</div>
        @else
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>Voter</th>
                        <th>Member ID</th>
                        <th>District</th>
                        <th>Town</th>
                        <th>Candidate</th>
                        <th>Voted At</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($votes as $vote)
                        <tr>
                            <td>{{ $vote->voter_name ?? '—' }}</td>
                            <td>{{ $vote->member_id ?? '—' }}</td>
                            <td>{{ $vote->district ?? '—' }}</td>
                            <td>{{ $vote->town ?? '—' }}</td>
                            <td>{{ $vote->candidate_name ?? '—' }}</td>
                            <td>{{ $vote->voted_at ? \Carbon\Carbon::parse($vote->voted_at)->format('Y-m-d H:i:s') : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
@endsection
