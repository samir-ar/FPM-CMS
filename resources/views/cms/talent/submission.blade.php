@extends($layout)

@section('content')
@php
    $field = function ($label, $value) {
        if ($value === null || $value === '') { return ''; }
        return '<tr><th style="width:230px;vertical-align:top">' . e($label) . '</th><td style="white-space:pre-line"><bdi>' . e($value) . '</bdi></td></tr>';
    };
@endphp
<section class="content-header">
    <h1>FPM Talent <small>submission #{{ $s->id }}</small></h1>
</section>

<section class="content">
    @if (session('message'))
        <div class="alert alert-success">{{ session('message') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <p><a href="{{ route('admin.talent-submissions.index') }}">&larr; All submissions</a></p>

    <div class="row">
        <div class="col-md-8">
            <div class="box box-default">
                <div class="box-header with-border"><h3 class="box-title">{{ $trackTitle ?: $s->track }} &middot; cycle {{ $s->cycle }} &middot; {{ $s->created_at }}</h3></div>
                <div class="box-body">
                    <table class="table table-bordered">
                        {!! $field($s->is_guest ? 'Sent by (guest)' : 'Sent by (member)', $s->submitter_name) !!}
                        {!! $field('Member ID', $s->member_id) !!}
                        {!! $field('Phone', $s->submitter_phone) !!}
                        {!! $field('Email', $s->submitter_email) !!}
                        {!! $field('District', $s->submitter_district) !!}
                        {!! $field('Town', $s->submitter_town) !!}
                        {!! $field('Organizational role', $s->submitter_role) !!}
                        {!! $field('Nominee', $s->nominee_name) !!}
                        {!! $field('Nominee district', $s->nominee_district) !!}
                        {!! $field('Nominee town', $s->nominee_town) !!}
                        {!! $field('Profession / specialty', $s->profession) !!}
                        {!! $field('Nominee relation to FPM', $s->relation_to_fpm) !!}
                        {!! $field('Relation between the two', $s->relationship_between) !!}
                        {!! $field('Reasons', $s->reasons) !!}
                        {!! $field('Strengths and qualifications', $s->strengths) !!}
                        {!! $field('Personal information', $s->personal_info) !!}
                        {!! $field('Education level', $s->education_level) !!}
                        {!! $field('Academic qualifications', $s->academic) !!}
                        {!! $field('Professional experience', $s->professional) !!}
                        {!! $field('Organizational / public experience', $s->organizational) !!}
                        {!! $field('Areas of interest', $s->interests) !!}
                        {!! $field('Cover letter', $s->cover_letter) !!}
                        <tr>
                            <th>Roles</th>
                            <td>
                                @foreach ($s->roles() as $r)
                                    <span class="label label-default" style="display:inline-block;margin:2px"><bdi>{{ $r['name'] }}@if (!empty($r['other'])): {{ $r['other'] }}@endif</bdi></span>
                                @endforeach
                            </td>
                        </tr>
                        <tr><th>Ticked the declaration</th><td>{{ $s->acknowledged ? 'Yes' : 'No' }}</td></tr>
                    </table>
                </div>
            </div>

            <div class="box box-default">
                <div class="box-header with-border"><h3 class="box-title">Files</h3></div>
                <div class="box-body">
                    @forelse ($s->attachments as $a)
                        @php $previewType = \App\Http\Controllers\Admin\TalentSubmissionsController::previewTypeOf($a->path); @endphp
                        <p>
                            <i class="fa fa-file-o"></i>
                            <b>{{ $a->kind === 'cv' ? 'CV' : 'Document' }}</b> &mdash; <bdi>{{ $a->original_name }}</bdi> ({{ number_format($a->size / 1024, 0) }} KB)
                            @if ($canSeeFiles)
                                @if ($previewType)
                                    &nbsp;<button type="button" class="btn btn-xs btn-primary js-preview" data-target="preview-{{ $a->id }}" data-src="{{ route('admin.talent-files.preview', $a->id) }}" data-type="{{ $previewType }}"><i class="fa fa-eye"></i> Preview</button>
                                    &nbsp;<a class="btn btn-xs btn-default" target="_blank" href="{{ route('admin.talent-files.preview', $a->id) }}">Open in a new tab</a>
                                @endif
                                &nbsp;<a class="btn btn-xs btn-default" href="{{ route('admin.talent-files.download', $a->id) }}">Download</a>
                                @unless ($previewType)
                                    <small class="text-muted">(no preview for this file type, download it)</small>
                                @endunless
                            @else
                                &nbsp;<span class="text-muted">(no permission to download: ask for "Talent CV Files")</span>
                            @endif
                        </p>
                        @if ($canSeeFiles && $previewType)
                            {{-- loaded only when the Preview button is pressed, so the file is not fetched for nothing --}}
                            <div id="preview-{{ $a->id }}" style="display:none;margin:0 0 18px"></div>
                        @endif
                    @empty
                        <p class="text-muted">No files.</p>
                    @endforelse
                    @if ($canSeeFiles)
                    <script>
                        (function () {
                            document.querySelectorAll('.js-preview').forEach(function (btn) {
                                btn.addEventListener('click', function () {
                                    var box = document.getElementById(btn.getAttribute('data-target'));
                                    if (box.style.display === 'block') { box.style.display = 'none'; box.innerHTML = ''; btn.innerHTML = '<i class="fa fa-eye"></i> Preview'; return; }
                                    var src = btn.getAttribute('data-src');
                                    box.innerHTML = btn.getAttribute('data-type') === 'application/pdf'
                                        ? '<iframe src="' + src + '" style="width:100%;height:720px;border:1px solid #ddd"></iframe>'
                                        : '<img src="' + src + '" style="max-width:100%;border:1px solid #ddd">';
                                    box.style.display = 'block';
                                    btn.innerHTML = '<i class="fa fa-eye-slash"></i> Hide preview';
                                });
                            });
                        })();
                    </script>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="box box-primary">
                <div class="box-header with-border"><h3 class="box-title">Review</h3></div>
                <div class="box-body">
                    <form method="POST" action="{{ route('admin.talent-submissions.update', $s->id) }}">
                        @csrf
                        <div class="form-group">
                            <label>Status</label>
                            <select name="status" class="form-control">
                                @foreach ($statuses as $key => $label)
                                    <option value="{{ $key }}" @if ($s->status === $key) selected @endif>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Private notes (never shown to members)</label>
                            <textarea name="admin_notes" rows="6" class="form-control">{{ old('admin_notes', $s->admin_notes) }}</textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">Save</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
