@extends('layouts.cms')

@section('content')
<div class="box box-default">
    <div class="box-header with-border">
        <h3 class="box-title">{{ $election->title }} — Voter-List Viewers</h3>
    </div>
    <div class="box-body">
        <p class="text-muted">
            Lets a specific non-candidate (e.g. campaign or party staff) see one district's voters for
            this election, the same view a candidate running in that district gets — without making
            them an actual candidate.
        </p>

        @if(session('message'))
            <div class="alert alert-success">{{ session('message') }}</div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('admin.internal-election.voter-viewers.store', $election->id) }}" class="form-inline" style="margin-bottom: 20px;">
            @csrf
            <div class="form-group" style="margin-right: 10px;">
                <label for="member_id" style="margin-right: 6px;">FPM ID</label>
                <input type="text" name="member_id" id="member_id" class="form-control" required>
            </div>
            <div class="form-group" style="margin-right: 10px;">
                <label for="election_state_id" style="margin-right: 6px;">District</label>
                <select name="election_state_id" id="election_state_id" class="form-control" required>
                    <option value="">— Select a district —</option>
                    @foreach($states as $state)
                        <option value="{{ $state->id }}">{{ $state->name }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Add</button>
        </form>

        @if($viewers->isEmpty())
            <div class="alert alert-info">No extra viewers added yet.</div>
        @else
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>FPM ID</th>
                        <th>Name</th>
                        <th>District</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($viewers as $viewer)
                        <tr>
                            <td>{{ $viewer->member_id }}</td>
                            <td>{{ $viewer->resolved_name ?? '—' }}</td>
                            <td>{{ $viewer->electionState->name ?? '—' }}</td>
                            <td>
                                <form method="POST" action="{{ route('admin.internal-election.voter-viewers.destroy', [$election->id, $viewer->id]) }}" onsubmit="return confirm('Remove this viewer?');" style="display:inline;">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-sm">Remove</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
@endsection
