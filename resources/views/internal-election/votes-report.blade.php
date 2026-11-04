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
        <form method="GET" class="row" style="margin-bottom: 15px;">
            <input type="hidden" name="sort" value="{{ $sortDir }}">
            <div class="col-sm-4">
                <label for="state_id">District</label>
                <select name="state_id" id="state_id" class="form-control" onchange="this.form.submit()">
                    <option value="">— All districts —</option>
                    @foreach($allStates as $state)
                        <option value="{{ $state->id }}" {{ $selectedStateId === $state->id ? 'selected' : '' }}>
                            {{ $state->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-sm-4">
                <label for="town">Town</label>
                <select name="town" id="town" class="form-control" onchange="this.form.submit()">
                    <option value="">— All towns —</option>
                    @foreach($towns as $town)
                        <option value="{{ $town }}" {{ $selectedTown === $town ? 'selected' : '' }}>
                            {{ $town }}
                        </option>
                    @endforeach
                    <option value="__unspecified__" {{ $selectedTown === '' ? 'selected' : '' }}>(no town on file)</option>
                </select>
            </div>
        </form>

        @if($votes->isEmpty())
            <div class="alert alert-warning">No votes match the current filters.</div>
        @else
            <p class="text-muted">{{ number_format($votes->total()) }} {{ Str::plural('vote', $votes->total()) }}</p>
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>Voter</th>
                        <th>Member ID</th>
                        <th>District</th>
                        <th>Town</th>
                        <th>Candidate</th>
                        <th>
                            <a href="{{ request()->fullUrlWithQuery(['sort' => $sortDir === 'asc' ? 'desc' : 'asc', 'page' => 1]) }}">
                                Voted At {!! $sortDir === 'asc' ? '&uarr;' : '&darr;' !!}
                            </a>
                        </th>
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
            {{ $votes->onEachSide(1)->links() }}
        @endif
    </div>
</div>
@endsection
