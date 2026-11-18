{{-- One phone, as the reports show it: its make/model, its hardware id (survives a reinstall) and the id the app made for this installation. --}}
@if($model !== '')<strong>{{ $model }}</strong><br>@endif
@if($hardware !== '')<span class="dev" title="The phone's own id, as the operating system gives it to this app">HW&nbsp;{{ $hardware }}</span><br>@endif
@if($device !== '')<span class="dev" style="background:#f6f7f8;" title="Made by the app on this installation; a reinstall makes a new one">App&nbsp;{{ $device }}</span>@endif
@if($hardware === '' && $device === '' && $model === '')<span class="text-muted">not recorded</span>@endif
