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

    // Named actions that can be granted independently on top of "View"
    // (without going all the way to "Full") for specific pages. Add an
    // entry here + gate the matching route with page-perm:<id>,action:<key>
    // to offer the same fine-grained toggle for another page later.
    private const PAGE_ACTIONS = [
        77 => [ // Check-In Events
            'attendance.store'  => 'Can add people to check-in (attendance)',
            'attendance.export' => 'Can export attendance to Excel',
        ],
    ];

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
        $currentExtraActions = $profile->exists
            ? \App\V2\ProfilePermission::where('profile_id', $profile->id)->pluck('extra_actions', 'page_id')->all()
            : [];

        $pages = Page::whereNull('parent_id')->orderBy('name')->get();
        $pageFields = [];
        foreach ($pages as $page) {
            // Built as ONE combined col-md-4 cell (select + any extra-action
            // checkboxes together), not as separate flat entries — Bootstrap's
            // float grid here doesn't equalize row heights, so 3 independently
            // floated fields can drift apart and land under the wrong
            // neighboring page whenever an earlier label wraps to 2 lines.
            // Grouping them into one div guarantees they stay together.
            $group = $this->drawHtml(
                'select-box',
                $page->name,
                "permissions[{$page->id}]",
                $currentLevels[$page->id] ?? 'none',
                ['none' => 'None', 'view' => 'View', 'full' => 'Full Control'],
                null,
                '' // no column class here — the outer wrapper below carries it
            );

            // Extra per-action checkboxes for pages that have any defined
            // (currently just Check-In Events) — grantable independently
            // of the level above, so a View profile can still be given
            // just "add people" or just "export" without full access.
            // Only meaningful when the level is View (Full already implies
            // every action, None means no access at all) — hidden by JS
            // below unless View is the currently-selected value.
            if ($pageActions = self::PAGE_ACTIONS[$page->id] ?? null) {
                $checkboxes = '';
                foreach ($pageActions as $actionKey => $label) {
                    $granted = in_array($actionKey, $currentExtraActions[$page->id] ?? [], true);
                    $checkboxes .= $this->drawHtml(
                        'checkbox',
                        '&nbsp;&nbsp;&nbsp;&nbsp;↳ ' . $label,
                        "extra_actions[{$page->id}][]",
                        $granted,
                        $actionKey,
                        null,
                        ''
                    );
                }
                $group .= '<div class="extra-actions" data-for-page="' . $page->id . '">' . $checkboxes . '</div>';
            }

            $pageFields[] = '<div class="col-md-4">' . $group . '</div>';
        }

        // Show a page's extra-action checkboxes only while its level is
        // set to View — hide them for None/Full, where they don't apply.
        $pageFields[] = '<script>
            $(function () {
                function syncExtraActions() {
                    $(".extra-actions").each(function () {
                        var pageId = $(this).data("for-page");
                        var level = $(\'select[name="permissions[\' + pageId + \']"]\').val();
                        $(this).toggle(level === "view");
                    });
                }
                syncExtraActions();
                $(document).on("change", "select[name^=\'permissions[\']", syncExtraActions);
            });
        </script>';

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
                    'box-header'    => 'Page Access — View/Full is enforced (blocks actual actions, not just the sidebar) on every page. A few pages also offer specific extra actions (indented, ↳) that can be granted on top of View without giving Full access.',
                    'form_fields'   => $pageFields,
                ],
            ],
        ]);
    }

    private function savePermissions(Profile $profile, Request $request)
    {
        $profile->permissions()->delete();

        $extraActionsByPage = (array) $request->input('extra_actions', []);

        $rows = [];
        foreach ((array) $request->input('permissions', []) as $pageId => $level) {
            if ($level === 'view' || $level === 'full') {
                $rows[] = [
                    'profile_id'     => $profile->id,
                    'page_id'        => (int) $pageId,
                    'level'          => $level,
                    'extra_actions'  => !empty($extraActionsByPage[$pageId])
                        ? json_encode(array_values($extraActionsByPage[$pageId]))
                        : null,
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ];
            }
        }

        if (!empty($rows)) {
            \DB::table('profile_permissions')->insert($rows);
        }
    }
}
