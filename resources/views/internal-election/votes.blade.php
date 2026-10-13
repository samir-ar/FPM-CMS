@extends('layouts.cms')

@section('content')
<div class="box box-default">
    <div class="box-header with-border">
        <h3 class="box-title">{{ $election->title }} — Vote Dispute Review</h3>
    </div>
    <div class="box-body">
        <form method="GET" class="form-inline" style="margin-bottom: 15px;">
            <div class="form-group">
                <label for="state_id">District</label>
                <select name="state_id" id="state_id" class="form-control" onchange="this.form.submit()">
                    <option value="">— Select a district —</option>
                    @foreach($allStates as $state)
                        <option value="{{ $state->id }}" {{ $selectedStateId == $state->id ? 'selected' : '' }}>
                            {{ $state->name }}
                        </option>
                    @endforeach
                </select>
            </div>
        </form>

        @if(!$selectedStateId)
            <div class="alert alert-info">Select a district above to list its votes for this election.</div>
        @elseif($votes->isEmpty())
            <div class="alert alert-warning">No votes recorded yet for this district.</div>
        @else
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>Voter</th>
                        <th>Member ID</th>
                        <th>Candidate</th>
                        <th>Voted At</th>
                        <th>Selfie (captured at vote time)</th>
                        <th>On-file Photo</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($votes as $vote)
                        <tr>
                            <td>{{ $vote->user->name ?? '—' }}</td>
                            <td>{{ $vote->user->member_id ?? '—' }}</td>
                            <td>{{ $vote->candidate->name ?? '—' }}</td>
                            <td>{{ $vote->voted_at ? \Carbon\Carbon::parse($vote->voted_at)->format('Y-m-d H:i:s') : '—' }}</td>
                            <td>
                                @if($vote->selfie_url)
                                    <img src="{{ $vote->selfie_url }}" style="width:100px;height:100px;object-fit:cover;" onerror="this.style.visibility='hidden'">
                                @else
                                    <span class="text-muted">none</span>
                                @endif
                            </td>
                            <td>
                                @if($vote->user && $vote->user->member_id)
                                    <img src="/api/v2/member-photo/{{ $vote->user->member_id }}" style="width:100px;height:100px;object-fit:cover;" onerror="this.style.visibility='hidden'">
                                @else
                                    <span class="text-muted">none</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
@endsection
