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
            @if($selectedStateId)
                <div class="form-group" style="margin-left: 10px;">
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
                <div class="checkbox" style="margin-left: 15px; display: inline-block; margin-top: 25px;">
                    <label>
                        <input type="checkbox" name="only_duplicate_devices" value="1" onchange="this.form.submit()" {{ $onlyDuplicateDevices ? 'checked' : '' }}>
                        Show only reused devices
                    </label>
                </div>
            @endif
        </form>

        @if($suspiciousDeviceIds->isNotEmpty())
            <div class="alert alert-danger">
                ⚠ {{ $suspiciousDeviceIds->count() }} device(s) were used to cast votes for more than one member in this election — rows are highlighted below (across all districts, not just the one selected).
            </div>
        @endif

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
                        <th>Voted At</th>
                        <th>Phone / Device ID</th>
                        <th>Selfie (captured at vote time)</th>
                        <th>On-file Photo</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($votes as $vote)
                        @php
                            $isSuspicious = $vote->device_id && $suspiciousDeviceIds->contains($vote->device_id);
                        @endphp
                        <tr class="{{ $isSuspicious ? 'danger' : '' }}">
                            <td>{{ $vote->user->name ?? '—' }}</td>
                            <td>{{ $vote->user->member_id ?? '—' }}</td>
                            <td>{{ $vote->voted_at ? \Carbon\Carbon::parse($vote->voted_at)->format('Y-m-d H:i:s') : '—' }}</td>
                            <td>
                                @if(!empty($vote->device_model))<strong>{{ $vote->device_model }}</strong><br>@endif
                                {{ $vote->device_id ?? '—' }}
                                @if($isSuspicious)
                                    <span class="label label-danger">reused</span>
                                @endif
                            </td>
                            <td>
                                @if($vote->selfie_url)
                                    <img src="{{ $vote->selfie_url }}" style="width:100px;height:100px;object-fit:cover;cursor:pointer;" onerror="this.style.visibility='hidden'" onclick="showEnlargedImage(this.src)">
                                @else
                                    <span class="text-muted">none</span>
                                @endif
                            </td>
                            <td>
                                @if($vote->user && $vote->user->member_id)
                                    <img src="/api/v2/member-photo/{{ $vote->user->member_id }}" style="width:100px;height:100px;object-fit:cover;cursor:pointer;" onerror="this.style.visibility='hidden'" onclick="showEnlargedImage(this.src)">
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

<div id="imageEnlargeOverlay" onclick="this.style.display='none'" style="display:none;position:fixed;z-index:9999;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.85);cursor:zoom-out;text-align:center;">
    <img id="imageEnlargeOverlayImg" style="max-width:90%;max-height:90%;margin-top:5vh;box-shadow:0 0 20px rgba(0,0,0,0.5);">
</div>
<script>
    function showEnlargedImage(src) {
        document.getElementById('imageEnlargeOverlayImg').src = src;
        document.getElementById('imageEnlargeOverlay').style.display = 'block';
    }
</script>
@endsection
