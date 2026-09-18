<?php

namespace App\Http\Controllers\Admin;

use App\Page;
use App\Http\Controllers\Controller;
use App\Http\Traits\FormTrait;
use App\V2\Profile;
use Illuminate\Http\Request;

class ProfilesController extends Controller
{
    use FormTrait;

    public function index()
    {
        return view('components.table')->with([
            'layout'      => 'layouts.cms',
            'pageTitle'   => 'Profiles',
            'table_title' => 'Profiles',
            'slug'        => 'profiles',
            'headers'     => ['id', 'Name', 'Admins Assigned', 'Action'],
            'custom_btn'  => "<a href='" . route('admin.profiles.create') . "' class='btn btn-primary'>Add Profile</a>",
            'rows'        => Profile::withCount('admins')->get()->map(function ($p) {
                return [
                    $p->id,
                    $p->name,
                    $p->admins_count,
                    "<a class='edit-link' href='" . route('admin.profiles.edit', $p->id) . "'>" .
                        "<i class='fa fa-edit'></i></a>" .
                        "<a data-toggle='modal' class='delete-link' href='#deleteModal' id='" . route('admin.profiles.destroy', $p->id) . "'>" .
                        "<i class='fa fa-trash' style='color: red;' aria-hidden='true'></i>",
                ];
            }),
        ]);
    }

    public function create()
    {
        return $this->form(new Profile(), route('admin.profiles.store'), 'create');
    }

    public function store(Request $request)
    {
        $this->validate($request, ['name' => 'required']);

        $profile = Profile::create(['name' => $request->name]);
        $this->savePermissions($profile, $request);

        return redirect()->route('admin.profiles.index')->with('message', 'Profile created.');
    }

    public function edit($id)
    {
        $profile = Profile::findOrFail($id);

        return $this->form($profile, route('admin.profiles.update', $id), 'update');
    }

    public function update($id, Request $request)
    {
        $this->validate($request, ['name' => 'required']);

        $profile = Profile::findOrFail($id);
        $profile->update(['name' => $request->name]);
        $this->savePermissions($profile, $request);

        return redirect()->route('admin.profiles.index')->with('message', 'Profile updated.');
    }

    public function destroy($id)
    {
        $profile = Profile::findOrFail($id);

        if ($profile->admins()->exists()) {
            return back()->with('message', 'Cannot delete a profile that still has admins assigned to it. Reassign them first.');
        }

        $profile->delete();

        return back()->with('message', 'Profile deleted.');
    }

    private function form(Profile $profile, string $action, string $method)
    {
        $currentLevels = $profile->exists ? $profile->permissionMap() : [];

        $pages = Page::whereNull('parent_id')->orderBy('name')->get();
        $pageFields = [];
        foreach ($pages as $page) {
            $pageFields[] = $this->drawHtml(
                'select-box',
                $page->name,
                "permissions[{$page->id}]",
                $currentLevels[$page->id] ?? 'none',
                ['none' => 'None', 'view' => 'View', 'full' => 'Full Control'],
                null,
                'col-md-4'
            );
        }

        return view('components.form')->with([
            'layout'      => 'layouts.cms',
            'pageTitle'   => $profile->exists ? 'Edit Profile' : 'Add Profile',
            'method'      => $method,
            'form_action' => $action,
            'boxes' => [
                [
                    'wrapper-class' => 'col-md-12',
                    'class'         => 'box-default',
                    'box-header'    => 'Profile',
                    'form_fields'   => [
                        $this->drawHtml('small_text', 'Name', 'name', $profile->name, null, '', 'col-md-4 required'),
                    ],
                ],
                [
                    'wrapper-class' => 'col-md-12',
                    'class'         => 'box-default',
                    // Only Archive, Check-In Events, and APP USERS actually
                    // enforce the View/Full distinction right now (Stage 1
                    // pilot) — everywhere else, any level above None just
                    // controls sidebar visibility, same as the old system.
                    'box-header'    => 'Page Access — Full/View is only enforced today on Archive, Check-In Events, and APP USERS. Elsewhere, any level above None controls sidebar visibility only.',
                    'form_fields'   => $pageFields,
                ],
            ],
        ]);
    }

    private function savePermissions(Profile $profile, Request $request)
    {
        $profile->permissions()->delete();

        $rows = [];
        foreach ((array) $request->input('permissions', []) as $pageId => $level) {
            if ($level === 'view' || $level === 'full') {
                $rows[] = [
                    'profile_id' => $profile->id,
                    'page_id'    => (int) $pageId,
                    'level'      => $level,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        if (!empty($rows)) {
            \DB::table('profile_permissions')->insert($rows);
        }
    }
}
