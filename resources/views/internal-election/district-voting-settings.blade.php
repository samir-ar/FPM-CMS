@extends('layouts.cms')

@section('content')
<div class="box box-default">
    <div class="box-header with-border">
        <h3 class="box-title">District Voting Settings — {{ $election->title }}</h3>
    </div>
    <div class="box-body">
        <p class="text-muted">
            Turn a district <strong>off</strong> when its result is already agreed/uncontested (1-3 candidates
            with no real contest, or an agreed رديف among 4) — voters in that district won't see a ballot at
            all, and the Results report will rank candidates by their <strong>Order</strong> field (set on the
            Candidates page) instead of vote count. Leave a district <strong>on</strong> for a normal contested race.
        </p>

        @if(session('message'))
            <div class="alert alert-success">{{ session('message') }}</div>
        @endif

        <form method="POST" action="{{ route('admin.internal-election.district-voting-settings.update', $election->id) }}">
            @csrf
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th style="width: 150px;">Voting</th>
                        <th>District</th>
                        <th style="width: 150px;">Candidates</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($states as $state)
                        @php
                            $isEnabled = $settingsByStateId->has($state->id) ? (bool) $settingsByStateId->get($state->id) : true;
                        @endphp
                        <tr>
                            <td>
                                <label style="font-weight:normal;">
                                    <input type="checkbox" name="enabled_states[]" value="{{ $state->id }}" {{ $isEnabled ? 'checked' : '' }}>
                                    {{ $isEnabled ? 'On' : 'Off' }}
                                </label>
                            </td>
                            <td>{{ $state->name }}</td>
                            <td>{{ $candidateCounts->get($state->id, 0) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <button type="submit" class="btn btn-primary">Save</button>
        </form>
    </div>
</div>
@endsection
