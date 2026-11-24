@extends($layout)

@section('content')
@php
    $names = ['peer' => 'Track 1: naming others', 'self' => 'Track 2: showing interest'];
    $kindNames = ['section' => 'Section title', 'field' => 'Question', 'roles' => 'Roles to tick', 'location' => 'District + town', 'cv' => 'CV upload', 'docs' => 'Supporting documents', 'guest' => 'Guest details'];
    $inputNames = ['text' => 'Short text', 'textarea' => 'Long text', 'dropdown' => 'Pick from a list'];
@endphp
<section class="content-header">
    <h1>FPM Talent <small>edit the form of a track</small></h1>
</section>

<section class="content">
    @if (session('message'))
        <div class="alert alert-success">{{ session('message') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <ul class="nav nav-tabs" style="margin-bottom:15px">
        @foreach ($tracks as $t)
            <li class="{{ $t->code === $track->code ? 'active' : '' }}"><a href="{{ route('admin.talent-form.edit', $t->code) }}">{{ $names[$t->code] ?? $t->code }}</a></li>
        @endforeach
        <li><a href="{{ route('admin.talent-tracks.index') }}"><i class="fa fa-arrow-left"></i> Back to the tracks</a></li>
    </ul>

    <div class="callout callout-info">
        This is the form the member sees, top to bottom. Use the arrows to <b>move</b> a row. <b>Show</b> unticked = the row is <b>hidden</b> in the app
        (nothing is lost, tick it again to bring it back). Rows you add yourself can be <b>removed</b> for good.
        <b>Required</b> = the member cannot send without it.
        The track's welcome text and the sentence the member must tick are edited in <a href="{{ route('admin.talent-tracks.index') }}">Talent Tracks</a>.
        Changes reach the app the next time a member opens the screen. <b>Apps released before this feature keep their own fixed form</b>, so a question you add may not appear for them until they update.
    </div>

    <form method="POST" action="{{ route('admin.talent-form.update', $track->code) }}" id="formEditor">
        @csrf
        <div class="box box-primary">
            <div class="box-header with-border"><h3 class="box-title">{{ $names[$track->code] ?? $track->code }}</h3></div>
            <div class="box-body no-padding">
                <table class="table table-bordered" id="itemsTable" style="margin:0">
                    <thead>
                        <tr><th style="width:70px">Move</th><th style="width:150px">Type</th><th>Text shown in the app</th><th style="width:36%">Details</th><th style="width:80px">Required</th><th style="width:70px">Show</th><th style="width:90px"></th></tr>
                    </thead>
                    <tbody id="itemsBody">
                        @foreach ($items as $i)
                            @include('cms.talent.form_row', ['i' => $i, 'kindNames' => $kindNames, 'inputNames' => $inputNames])
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="box-footer">
                <button type="button" class="btn btn-default" id="addQuestion"><i class="fa fa-plus"></i> Add a question</button>
                <button type="button" class="btn btn-default" id="addSection"><i class="fa fa-plus"></i> Add a section title</button>
                <button type="submit" class="btn btn-primary pull-right">Save the form</button>
            </div>
        </div>
    </form>
</section>

{{-- the rows added with the buttons --}}
<table style="display:none"><tbody>
    <template id="tplQuestion">
        @include('cms.talent.form_row', ['i' => null, 'new' => 'field', 'kindNames' => $kindNames, 'inputNames' => $inputNames])
    </template>
    <template id="tplSection">
        @include('cms.talent.form_row', ['i' => null, 'new' => 'section', 'kindNames' => $kindNames, 'inputNames' => $inputNames])
    </template>
</tbody></table>
@endsection

@section('footer_resources')
<script>
(function () {
    var body = document.getElementById('itemsBody');

    function setOptionsVisibility(row) {
        var sel = row.querySelector('.in-input');
        var opt = row.querySelector('.in-options');
        if (sel && opt) { opt.style.display = sel.value === 'dropdown' ? '' : 'none'; }
    }
    Array.prototype.forEach.call(body.querySelectorAll('tr'), setOptionsVisibility);

    body.addEventListener('change', function (e) {
        if (e.target.classList.contains('in-input')) { setOptionsVisibility(e.target.closest('tr')); }
    });

    body.addEventListener('click', function (e) {
        var btn = e.target.closest('button[data-act]');
        if (!btn) { return; }
        var row = btn.closest('tr');
        var act = btn.getAttribute('data-act');
        if (act === 'up' && row.previousElementSibling) { body.insertBefore(row, row.previousElementSibling); }
        if (act === 'down' && row.nextElementSibling) { body.insertBefore(row.nextElementSibling, row); }
        if (act === 'remove') {
            if (!row.querySelector('.in-id').value) { row.remove(); return; }       // a row not saved yet: just drop it
            var del = row.querySelector('.in-delete');
            del.value = del.value ? '' : '1';
            row.style.opacity = del.value ? '.35' : '';
            btn.textContent = del.value ? 'Undo' : 'Remove';
        }
    });

    function add(tplId) {
        var tpl = document.getElementById(tplId);
        var holder = document.createElement('tbody');
        holder.innerHTML = tpl.innerHTML;
        var row = holder.querySelector('tr');
        body.appendChild(row);
        setOptionsVisibility(row);
        var first = row.querySelector('input[type=text]'); if (first) { first.focus(); }
        row.scrollIntoView({ block: 'center' });
    }
    document.getElementById('addQuestion').addEventListener('click', function () { add('tplQuestion'); });
    document.getElementById('addSection').addEventListener('click', function () { add('tplSection'); });

    // check the rows in the browser first, so a mistake never costs what was typed
    document.getElementById('formEditor').addEventListener('submit', function (e) {
        var problem = null;
        Array.prototype.forEach.call(body.querySelectorAll('tr'), function (row, n) {
            if (problem || row.querySelector('.in-delete').value) { return; }
            var label = row.querySelector('input[name$="[label]"]');
            if (label && !label.value.trim()) { problem = 'Row ' + (n + 1) + ': the text cannot be empty.'; }
            var sel = row.querySelector('.in-input'), opt = row.querySelector('.in-options');
            if (sel && sel.value === 'dropdown' && opt.value.split(/?
/).filter(function (l) { return l.trim(); }).length < 2) {
                problem = 'Row ' + (n + 1) + ': a pick-from-a-list question needs at least two choices (one per line).';
            }
        });
        if (problem) { e.preventDefault(); alert(problem); return; }
    });

    // the order on screen is the order saved: number the rows just before sending
    document.getElementById('formEditor').addEventListener('submit', function () {
        Array.prototype.forEach.call(body.querySelectorAll('tr'), function (row, n) {
            Array.prototype.forEach.call(row.querySelectorAll('[name]'), function (el) {
                el.name = el.name.replace(/^items\[[^\]]*\]/, 'items[' + n + ']');
            });
        });
    });
})();
</script>
@endsection
