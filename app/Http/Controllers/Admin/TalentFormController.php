<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\V2\TalentFormItem;
use App\V2\TalentTrack;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

// FPM Talent > Edit the form: every section, question and special block of the two forms (naming others / showing interest).
// The admin can rename, make required or optional, hide, reorder, and add questions or section titles of his own. Built-in
// items are hidden, never deleted (their answers live in their own columns); items added here can be deleted.
class TalentFormController extends Controller
{
    const MAX_ITEMS = 80;

    private function trackOr404(string $code): TalentTrack
    {
        return TalentTrack::where('code', $code)->firstOrFail();
    }

    public function edit($code)
    {
        $track = $this->trackOr404($code);

        return view('cms.talent.form')->with([
            'layout' => 'layouts.cms',
            'track' => $track,
            'tracks' => TalentTrack::orderBy('id')->get(),
            'items' => TalentFormItem::ordered($track->code),
        ]);
    }

    public function update(Request $request, $code)
    {
        $track = $this->trackOr404($code);
        $rows = array_values((array) $request->input('items', []));
        if (count($rows) > self::MAX_ITEMS) {
            return back()->withInput()->withErrors(['A form can have at most ' . self::MAX_ITEMS . ' rows.']);
        }

        $existing = TalentFormItem::ordered($track->code)->keyBy('id');

        // ---- check everything first: nothing is saved when one row is wrong
        foreach ($rows as $n => $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id && !$existing->has($id)) {
                return back()->withInput()->withErrors(['Row ' . ($n + 1) . ' does not belong to this form. Reload the page and try again.']);
            }
            $item = $id ? $existing[$id] : null;
            if ($item && !empty($row['delete'])) {
                continue;
            }
            $kind = $item ? $item->kind : (($row['kind'] ?? '') === 'section' ? 'section' : 'field');
            $label = trim((string) ($row['label'] ?? ''));
            if (in_array($kind, ['section', 'field', 'cv', 'location', 'guest'], true) && $label === '') {
                return back()->withInput()->withErrors(['Row ' . ($n + 1) . ': the title / question text cannot be empty.']);
            }
            if (mb_strlen($label) > 255) {
                return back()->withInput()->withErrors(['Row ' . ($n + 1) . ': the text is too long (255 characters at most).']);
            }
            $custom = !$item && $kind === 'field' || ($item && $item->isCustom());
            if ($custom && ($row['input'] ?? 'text') === 'dropdown') {
                $options = array_values(array_filter(array_map('trim', preg_split('/\R/u', (string) ($row['options'] ?? '')) ?: [])));
                if (count($options) < 2) {
                    return back()->withInput()->withErrors(['Row ' . ($n + 1) . ' "' . $label . '": a pick-from-a-list question needs at least two choices (one per line).']);
                }
            }
        }

        DB::transaction(function () use ($rows, $existing, $track) {
            $order = 0;
            foreach ($rows as $row) {
                $id = (int) ($row['id'] ?? 0);
                $item = $id ? $existing[$id] : null;
                $order += 10;

                if ($item) {
                    if (!empty($row['delete'])) {
                        if ($item->is_builtin) {
                            // a built-in item is hidden, not deleted: its answers keep their own column
                            if (!$item->is_locked) {
                                $item->update(['is_active' => false, 'sort_order' => $order]);
                            } else {
                                $item->update(['sort_order' => $order]);
                            }
                        } else {
                            $item->delete();
                        }
                        continue;
                    }
                    $this->fill($item, $row)->save();
                    $item->update(['sort_order' => $order]);
                    continue;
                }

                $kind = ($row['kind'] ?? '') === 'section' ? 'section' : 'field';
                $new = new TalentFormItem([
                    'track' => $track->code,
                    'kind' => $kind,
                    'key' => $kind === 'field' ? 'x_' . Str::lower(Str::random(8)) : null,
                    'is_builtin' => false,
                    'sort_order' => $order,
                ]);
                $this->fill($new, $row)->save();
            }
        });

        Log::info('talent_form_updated', ['track' => $track->code, 'admin_id' => optional($request->user())->id]);

        return redirect()->route('admin.talent-form.edit', $track->code)
            ->with('message', 'Saved. The app uses the new form the next time it opens the FPM Talent screen (apps released before this feature keep their own form).');
    }

    // what the admin may change of a row; the kind, key and the built-in input type never change
    private function fill(TalentFormItem $item, array $row): TalentFormItem
    {
        $label = trim((string) ($row['label'] ?? ''));
        $hint = trim((string) ($row['hint'] ?? ''));

        if ($label !== '') {
            $item->label = $label;
        }
        if (in_array($item->kind, ['field', 'roles', 'location'], true)) {
            $item->hint = $hint !== '' ? $hint : null;
        }
        if (in_array($item->kind, ['field', 'cv', 'location'], true)) {
            $item->required = !empty($row['required']);
        }
        if ($item->kind === 'field' && !$item->is_builtin) {
            $input = in_array($row['input'] ?? 'text', ['text', 'textarea', 'dropdown'], true) ? $row['input'] : 'text';
            $item->input = $input;
            $item->lines = $input === 'textarea' ? 4 : 1;
            $item->options = $input === 'dropdown' ? implode("\n", array_values(array_filter(array_map('trim', preg_split('/\R/u', (string) ($row['options'] ?? '')) ?: [])))) : null;
        }
        if (!$item->is_locked) {
            $item->is_active = !empty($row['active']);
        }

        return $item;
    }
}
