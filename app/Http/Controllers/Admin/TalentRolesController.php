<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\V2\TalentRole;
use Illuminate\Http\Request;

// FPM Talent > Roles: the list members tick from ("political leaders", "youth", ...). A role used by an old submission is
// never deleted (it stays in that submission), it is only switched off.
class TalentRolesController extends Controller
{
    public function index()
    {
        return view('cms.talent.roles')->with(['layout' => 'layouts.cms', 'roles' => TalentRole::orderBy('sort_order')->orderBy('id')->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:191']);
        TalentRole::create([
            'name' => trim($data['name']),
            'allows_text' => $request->boolean('allows_text'),
            'is_active' => true,
            'sort_order' => (int) TalentRole::max('sort_order') + 1,
        ]);

        return back()->with('message', 'Role added.');
    }

    // one Save for the whole list: name, order, on/off, "member writes his own text"
    public function update(Request $request)
    {
        $request->validate(['roles.*.name' => 'required|string|max:191', 'roles.*.sort_order' => 'nullable|integer|min:0|max:9999']);

        foreach ((array) $request->input('roles', []) as $id => $in) {
            $role = TalentRole::find($id);
            if (!$role) {
                continue;
            }
            $role->update([
                'name' => trim($in['name']),
                'sort_order' => (int) ($in['sort_order'] ?? $role->sort_order),
                'is_active' => !empty($in['is_active']),
                'allows_text' => !empty($in['allows_text']),
            ]);
        }

        return back()->with('message', 'Roles saved.');
    }
}
