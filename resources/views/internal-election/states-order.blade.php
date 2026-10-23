@extends('layouts.cms')

@section('content')
<div class="box box-default">
    <div class="box-header with-border">
        <h3 class="box-title">District Order</h3>
    </div>
    <div class="box-body">
        <p class="text-muted">
            Sets the order districts appear in on the Internal Election candidates list, which groups
            candidates by district then by age. Leave blank for alphabetical fallback (after any
            districts that do have a number).
        </p>

        @if(session('message'))
            <div class="alert alert-success">{{ session('message') }}</div>
        @endif

        <form method="POST" action="{{ route('admin.internal-election.states-order.update') }}">
            @csrf
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th style="width: 120px;">Order</th>
                        <th>District</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($states as $state)
                        <tr>
                            <td>
                                <input type="number" min="1" class="form-control" name="order[{{ $state->id }}]" value="{{ $state->display_order }}">
                            </td>
                            <td>{{ $state->name }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <button type="submit" class="btn btn-primary">Save Order</button>
        </form>
    </div>
</div>
@endsection
