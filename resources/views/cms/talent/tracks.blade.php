@extends($layout)

@section('content')
<section class="content-header">
    <h1>FPM Talent <small>tracks: name, texts, open window and audience</small></h1>
</section>

<section class="content">
    @if (session('message'))
        <div class="alert alert-success">{{ session('message') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="callout callout-info">
        A track is <b>closed</b> until you tick <b>Open</b> here. With dates, it opens at the start date and closes by itself at the end date.
        Before the start date and after the end date the card still shows in the app, locked, with the message below.
        <b>Public</b> (self-interest track only) lets guests, people without a member account, send the form after confirming their phone.
    </div>

    <form method="POST" action="{{ route('admin.talent-tracks.update') }}">
        @csrf
        <div class="row">
            @foreach ($tracks as $t)
                @php $state = $t->state(); @endphp
                <div class="col-md-6">
                    <div class="box box-primary">
                        <div class="box-header with-border">
                            <h3 class="box-title">{{ $t->code === 'peer' ? 'Track 1: naming others' : 'Track 2: showing interest' }}</h3>
                            <span class="pull-right label label-{{ $state === 'open' ? 'success' : ($state === 'upcoming' ? 'warning' : 'default') }}">{{ strtoupper($state) }} now</span>
                        </div>
                        <div class="box-body">
                            <div class="form-group">
                                <label>Title (the card in the app)</label>
                                <input type="text" class="form-control" dir="rtl" name="tracks[{{ $t->code }}][title]" value="{{ old('tracks.'.$t->code.'.title', $t->title) }}" required>
                            </div>
                            <div class="form-group">
                                <label>Small text under the title (optional)</label>
                                <input type="text" class="form-control" dir="rtl" name="tracks[{{ $t->code }}][subtitle]" value="{{ old('tracks.'.$t->code.'.subtitle', $t->subtitle) }}">
                            </div>

                            <div class="checkbox"><label>
                                <input type="checkbox" name="tracks[{{ $t->code }}][is_enabled]" value="1" @if (old('tracks.'.$t->code.'.is_enabled', $t->is_enabled)) checked @endif>
                                <b>Open</b> (members can send the form)
                            </label></div>

                            <div class="row">
                                <div class="col-sm-6 form-group">
                                    <label>Opens at</label>
                                    <input type="datetime-local" class="form-control" name="tracks[{{ $t->code }}][opens_at]" value="{{ old('tracks.'.$t->code.'.opens_at', $t->opens_at ? $t->opens_at->format('Y-m-d\TH:i') : '') }}">
                                </div>
                                <div class="col-sm-6 form-group">
                                    <label>Closes at</label>
                                    <input type="datetime-local" class="form-control" name="tracks[{{ $t->code }}][closes_at]" value="{{ old('tracks.'.$t->code.'.closes_at', $t->closes_at ? $t->closes_at->format('Y-m-d\TH:i') : '') }}">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-sm-6 form-group">
                                    <label>Who can see and use it</label>
                                    @if ($t->code === 'peer')
                                        <input type="text" class="form-control" value="Members only (always)" disabled>
                                        <input type="hidden" name="tracks[{{ $t->code }}][audience]" value="members">
                                    @else
                                        <select class="form-control" name="tracks[{{ $t->code }}][audience]">
                                            <option value="members" @if (old('tracks.'.$t->code.'.audience', $t->audience) === 'members') selected @endif>Members only</option>
                                            <option value="public" @if (old('tracks.'.$t->code.'.audience', $t->audience) === 'public') selected @endif>Public (members + guests)</option>
                                        </select>
                                    @endif
                                </div>
                                <div class="col-sm-6 form-group">
                                    <label>{{ $t->code === 'peer' ? 'Names a member may send per cycle' : 'Forms a member may send per cycle' }}</label>
                                    <input type="number" min="1" max="1000" class="form-control" name="tracks[{{ $t->code }}][max_per_member]" value="{{ old('tracks.'.$t->code.'.max_per_member', $t->max_per_member) }}" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Message on the locked card (when closed)</label>
                                <input type="text" class="form-control" dir="rtl" name="tracks[{{ $t->code }}][closed_message]" value="{{ old('tracks.'.$t->code.'.closed_message', $t->closed_message) }}">
                                <p class="help-block">Before the start date the app shows "opens on &lt;date&gt;" by itself.</p>
                            </div>
                            <div class="form-group">
                                <label>Welcome text above the form</label>
                                <textarea class="form-control" rows="5" dir="rtl" name="tracks[{{ $t->code }}][intro]">{{ old('tracks.'.$t->code.'.intro', $t->intro) }}</textarea>
                            </div>
                            <div class="form-group">
                                <label>The sentence the member must tick</label>
                                <textarea class="form-control" rows="3" dir="rtl" name="tracks[{{ $t->code }}][acknowledgement]">{{ old('tracks.'.$t->code.'.acknowledgement', $t->acknowledgement) }}</textarea>
                            </div>
                        </div>
                        <div class="box-footer">
                            Cycle <b>{{ $t->cycle }}</b> &middot; submissions in it: <b>{{ \App\V2\TalentSubmission::where('track', $t->code)->where('cycle', $t->cycle)->count() }}</b>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        <button type="submit" class="btn btn-primary">Save</button>
    </form>

    <h4 style="margin-top:30px">Start a new cycle</h4>
    <p class="text-muted">A new cycle gives every member a fresh allowance. Earlier submissions stay in the list under their old cycle number; nothing is deleted.</p>
    @foreach ($tracks as $t)
        <form method="POST" action="{{ route('admin.talent-tracks.new-cycle', $t->code) }}" style="display:inline-block;margin-right:8px"
              onsubmit="return confirm('Start cycle {{ $t->cycle + 1 }} for this track? Members get a fresh allowance.');">
            @csrf
            <button type="submit" class="btn btn-default">New cycle for {{ $t->code === 'peer' ? 'naming others' : 'showing interest' }} (now {{ $t->cycle }})</button>
        </form>
    @endforeach
</section>
@endsection
