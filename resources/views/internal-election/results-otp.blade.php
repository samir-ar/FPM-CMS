@extends('layouts.cms')

@section('content')
<div class="box box-default">
    <div class="box-header with-border">
        <h3 class="box-title">Verification Required</h3>
    </div>
    <div class="box-body">
        <p class="text-muted">
            This page shows election results / who voted for whom. A one-time code is required
            before it will open, sent to one fixed number regardless of which admin account you're
            logged in as. Valid for {{ \App\Http\Controllers\Admin\InternalElectionController::RESULTS_OTP_WINDOW_MINUTES }} minutes once verified.
        </p>

        @if(session('message'))
            <div class="alert alert-success">{{ session('message') }}</div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('admin.internal-election.results-otp.send') }}" style="margin-bottom: 20px;">
            @csrf
            <button type="submit" class="btn btn-default">
                {{ $codeSentAt ? 'Resend code' : 'Send code' }}
            </button>
            @if($codeSentAt)
                <span class="text-muted" style="margin-left: 10px;">Last sent {{ $codeSentAt->diffForHumans() }}</span>
            @endif
        </form>

        <form method="POST" action="{{ route('admin.internal-election.results-otp.verify') }}" class="form-inline">
            @csrf
            <div class="form-group" style="margin-right: 10px;">
                <label for="code" style="margin-right: 6px;">Code</label>
                <input type="text" name="code" id="code" class="form-control" autocomplete="off" required>
            </div>
            <button type="submit" class="btn btn-primary">Verify</button>
        </form>
    </div>
</div>
@endsection
