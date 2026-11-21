<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\V2\TalentChoice;
use Illuminate\Http\Request;

// FPM Talent > Choices: the drop-down lists of the forms. Add a value, rename it, change the order, switch it off. A value is
// switched off instead of deleted so old submissions keep their text.
class TalentChoicesController extends Controller
{
    public function index()
    {
        $lists = [];
        foreach (TalentChoice::GROUPS as $group => $title) {
            $lists[$group] = ['title' => $title, 'rows' => TalentChoice::where('group', $group)->orderBy('sort_order')->orderBy('id')->get()];
        }

        return view('cms.talent.choices')->with(['layout' => 'layouts.cms', 'lists' => $lists]);
    }

    public function update(Request $request)
    {
        $request->validate(['choices.*.name' => 'required|string|max:191', 'choices.*.sort_order' => 'nullable|integer|min:0|max:9999']);

        foreach ((array) $request->input('choices', []) as $id => $in) {
            $row = TalentChoice::find($id);
            if (!$row) {
                continue;
            }
            $row->update([
                'name' => trim($in['name']),
                'sort_order' => (int) ($in['sort_order'] ?? $row->sort_order),
                'is_active' => !empty($in['is_active']),
            ]);
        }

        return back()->with('message', 'Saved. The app uses the new lists the next time its FPM Talent screen loads.');
    }

    public function store(Request $request)
    {
        $data = $request->validate(['group' => 'required|in:' . implode(',', array_keys(TalentChoice::GROUPS)), 'name' => 'required|string|max:191']);
        TalentChoice::create([
            'group' => $data['group'],
            'name' => trim($data['name']),
            'is_active' => true,
            'sort_order' => (int) TalentChoice::where('group', $data['group'])->max('sort_order') + 1,
        ]);

        return back()->with('message', 'Added.');
    }
}
