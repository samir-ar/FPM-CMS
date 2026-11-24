@php
    // $i = an existing row, or null with $new = 'field' | 'section' for a row added with the buttons
    $kind = $i ? $i->kind : $new;
    $custom = !$i || !$i->is_builtin;
    $active = $i ? $i->is_active : true;
    $input = $i ? $i->input : 'text';
    $hasRequired = in_array($kind, ['field', 'cv', 'location'], true);
@endphp
<tr @if ($i && !$i->is_active) style="background:#f7f7f7" @endif>
    <td class="text-center" style="white-space:nowrap">
        <button type="button" class="btn btn-xs btn-default" data-act="up" title="Move up"><i class="fa fa-arrow-up"></i></button>
        <button type="button" class="btn btn-xs btn-default" data-act="down" title="Move down"><i class="fa fa-arrow-down"></i></button>
    </td>
    <td>
        <input type="hidden" class="in-id" name="items[0][id]" value="{{ $i ? $i->id : '' }}">
        <input type="hidden" name="items[0][kind]" value="{{ $kind }}">
        <input type="hidden" class="in-delete" name="items[0][delete]" value="">
        <span class="label label-{{ $kind === 'section' ? 'primary' : ($kind === 'field' ? 'info' : 'warning') }}">{{ $kindNames[$kind] ?? $kind }}</span>
        @if ($i && $i->audience === 'member')<br><small class="text-muted">members only</small>@endif
        @if ($i && $i->audience === 'guest')<br><small class="text-muted">guests only</small>@endif
        @if ($i && !$custom && $kind === 'field')<br><small class="text-muted">{{ $inputNames[$input] ?? $input }}</small>@endif
    </td>
    <td>
        @if ($kind === 'roles')
            <span class="text-muted">The list of roles (edited in Talent Roles)</span>
        @else
            <input type="text" class="form-control" dir="rtl" name="items[0][label]" value="{{ $i ? $i->label : '' }}"
                   placeholder="{{ $kind === 'section' ? 'Section title' : ($kind === 'location' ? 'District label' : 'The text shown in the app') }}" maxlength="255">
        @endif
    </td>
    <td>
        @if ($kind === 'field')
            <input type="text" class="form-control input-sm" dir="rtl" name="items[0][hint]" value="{{ $i ? $i->hint : '' }}" placeholder="Small text under the question (optional)">
            @if ($custom)
                <select class="form-control input-sm in-input" name="items[0][input]" style="margin-top:6px">
                    @foreach ($inputNames as $k => $n)<option value="{{ $k }}" @if ($input === $k) selected @endif>{{ $n }}</option>@endforeach
                </select>
                <textarea class="form-control input-sm in-options" dir="rtl" rows="3" name="items[0][options]" style="margin-top:6px" placeholder="The choices, one per line (at least two)">{{ $i ? $i->options : '' }}</textarea>
            @elseif ($input === 'dropdown')
                <small class="text-muted">The choices come from Talent Choices.</small>
            @endif
        @elseif ($kind === 'roles')
            <input type="text" class="form-control input-sm" dir="rtl" name="items[0][hint]" value="{{ $i ? $i->hint : '' }}" placeholder="Small text above the roles (optional)">
        @elseif ($kind === 'location')
            <input type="text" class="form-control input-sm" dir="rtl" name="items[0][hint]" value="{{ $i ? $i->hint : '' }}" placeholder="Town label">
        @elseif ($kind === 'guest')
            <small class="text-muted">Name, e-mail, district and town of a guest. A guest must always give them: this row can be moved or renamed, not hidden.</small>
        @elseif ($kind === 'cv')
            <small class="text-muted">The member attaches a PDF or Word file (5 MB at most).</small>
        @elseif ($kind === 'docs')
            <small class="text-muted">Optional files: PDF or pictures (up to 3).</small>
        @endif
    </td>
    <td class="text-center">
        @if ($hasRequired)
            <input type="checkbox" name="items[0][required]" value="1" @if (($i ? $i->required : false)) checked @endif>
        @elseif ($kind === 'roles' || $kind === 'guest')
            <small class="text-muted">always</small>
        @endif
    </td>
    <td class="text-center">
        <input type="checkbox" name="items[0][active]" value="1" @if ($active) checked @endif @if ($i && $i->is_locked) disabled @endif>
        @if ($i && $i->is_locked)<input type="hidden" name="items[0][active]" value="1">@endif
    </td>
    <td class="text-center">
        @if ($custom)
            <button type="button" class="btn btn-xs btn-danger" data-act="remove">Remove</button>
        @endif
    </td>
</tr>
