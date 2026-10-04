<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;

use Hash;
use App\Page;
use App\User;
use App\Http\Requests;
use App\Http\Traits\FormTrait;
use App\Http\Traits\FileTrait;
use Illuminate\Support\Str;
use App\Http\Controllers\Controller;

class AdminsController extends Controller
{
    use FormTrait;
    use FileTrait;

    public function index()
    {
        return view('components.table')->with([
            'layout'    => 'layouts.cms',
            'pageTitle'	=> 'Reports',
            'table_title' => 'Administrators',
            'slug'		=> 'admin',
            'headers'	=> ['id', 'Name', 'Email', 'Action'],
            'custom_btn' => "<a href='" . route('admin.admins.create') ."' class='btn btn-primary'>Add Admin</a>",
            'rows'		=> User::all()->map(function($r){
                return[
                    $r->id,
                    $r->name,
                    $r->email,
                    "<a class='edit-link' href='" . route('admin.admins.edit', $r->id) . "'>".
                    "<i class='fa fa-edit'></i>".
                    "<a data-toggle='modal' class='delete-link' href='#deleteModal' id='" .route('admin.admins.destroy', $r->id) . "'>".
                    "<i class='fa fa-trash' style='color: red;' aria-hidden='true'></i>",
                ];
            })
        ]);

    }

    // Shared by create()/edit(): renders every page as a checkbox, grouped
    // into one card per category with its children nested inside — a
    // sync() call only keeps whatever's actually rendered as a checkbox,
    // so leaving children out (as this used to) silently strips an admin's
    // granular permissions down to category-level on every save. Built as
    // raw HTML (rather than drawHtml()'s per-checkbox wrapper divs) so
    // categories and their children stay visually grouped instead of being
    // split across a generic two-column float grid.
    private function pageCheckboxes($admin = null)
    {
        $topLevelPages = Page::where('parent_id', null)->get();
        $cards = '';

        foreach ($topLevelPages as $page) {
            $checked = ($admin && $admin->hasPage($page->id)) ? 'checked' : '';

            $childrenHtml = '';
            foreach (Page::where('parent_id', $page->id)->get() as $child) {
                $childChecked = ($admin && $admin->hasPage($child->id)) ? 'checked' : '';
                $childrenHtml .= '<label style="display:block; font-weight:normal; color:#555; font-size:13px; margin-bottom:6px; cursor:pointer;">'
                    . '<input type="checkbox" name="pages[]" value="' . $child->id . '" class="minimal child-page-checkbox" ' . $childChecked . '> ' . e($child->name)
                    . '</label>';
            }

            $cards .= '<div class="permission-card" style="flex:1 1 260px; max-width:320px; border:1px solid #e3e3e3; border-radius:4px; padding:14px; background:#fff;">'
                . '<label style="display:flex; align-items:center; font-weight:600; font-size:14px; margin-bottom:0; cursor:pointer;">'
                . '<input type="checkbox" name="pages[]" value="' . $page->id . '" class="minimal category-checkbox" ' . $checked . ' style="margin-right:8px;"> ' . e($page->name)
                . '</label>'
                . ($childrenHtml ? '<div style="margin-left:4px; margin-top:10px; padding-left:12px; border-left:2px solid #eee;">' . $childrenHtml . '</div>' : '')
                . '</div>';
        }

        $toolbar = '<div style="margin-bottom:14px;">'
            . '<button type="button" id="pages-check-all" class="btn btn-default btn-sm">Check All</button> '
            . '<button type="button" id="pages-uncheck-all" class="btn btn-default btn-sm">Uncheck All</button>'
            . '</div>';

        $grid = '<div style="display:flex; flex-wrap:wrap; gap:14px;">' . $cards . '</div>';

        // Plain vanilla JS, not jQuery: this block renders inside @yield(\'content\'),
        // which this layout places BEFORE its jQuery <script> tag loads — a $(...)
        // call here would throw "jQuery is not defined" and silently kill the whole
        // block. Native addEventListener on document works regardless of load order
        // since it only needs to register now and fire later.
        $script = '<script>
            document.addEventListener("click", function(e){
                if (e.target && e.target.id === "pages-check-all") {
                    document.querySelectorAll("input[name=\'pages[]\']").forEach(function(cb){ cb.checked = true; });
                }
                if (e.target && e.target.id === "pages-uncheck-all") {
                    document.querySelectorAll("input[name=\'pages[]\']").forEach(function(cb){ cb.checked = false; });
                }
            });
            document.addEventListener("change", function(e){
                if (e.target && e.target.classList.contains("category-checkbox")) {
                    var card = e.target.closest(".permission-card");
                    card.querySelectorAll(".child-page-checkbox").forEach(function(cb){ cb.checked = e.target.checked; });
                }
                if (e.target && e.target.classList.contains("child-page-checkbox")) {
                    var card = e.target.closest(".permission-card");
                    var anyChecked = card.querySelector(".child-page-checkbox:checked") !== null;
                    if (anyChecked) {
                        card.querySelector(".category-checkbox").checked = true;
                    }
                }
            });
        </script>';

        return [$toolbar . $grid . $script];
    }

    public function edit($id)
    {
        $admin = User::find($id);

        $checkboxes = $this->pageCheckboxes($admin);

        return view('components.form')->with([
            'layout'         => 'layouts.cms',
            'pageTitle'		=> 'Update Admin',
            'method'		=> 'update',
            'form_action'	=> route('admin.admins.update', $id),

            'boxes' => [
                [
                    'wrapper-class' => 'col-md-4',
                    'class' => 'box-default',
                    'box-header' => 'Actions',
                    'form_fields' => [
                        $this->drawHtml('small_text', 'Email', 'email', $admin->email , null, '', 'col-md-12 '),
                        $this->drawHtml('small_text', 'Name', 'name', $admin->name, null, null, 'col-md-12'),
                        $this->drawHtml('small_text', 'New Password', 'password', '' , null, '', 'col-md-12 '),
                        $this->drawHtml('small_text', 'Confirm New Password', 'password_confirmation', '', null, null, 'col-md-12'),
                    ],
                ],
                [
                    'wrapper-class' => 'col-md-8',
                    'class' => 'box-default',
                    'box-header' => 'Permissions',
                    'form_fields' => $checkboxes,

                ],
            ]
        ]);
    }

    public function update($id, Request $request)
    {


        $this->validate($request,[
            'name' => '',
            'email' => 'required',
            'password' => 'confirmed',
        ]);

        $admin = User::find($id);

        if(request('password'))
            $admin->password = Hash::make(request('password'));

        $admin->email = request('email');
        $admin->name = request('name');

        $admin->save();

        if($pages = request('pages'))
            $admin->pages()->sync($pages);

        return redirect()->route('admin.admins.index')->with('message', 'Administrator updated');
    }

    public function create(Request $request)
    {
        $checkboxes = $this->pageCheckboxes();

        return view('components.form')->with([
            'layout'         => 'layouts.cms',
            'pageTitle'		=> 'Add Admin',
            'method'		=> 'post',
            'form_action'	=> route('admin.admins.store'),

            'boxes' => [
                [
                    'wrapper-class' => 'col-md-4',
                    'class' => 'box-default',
                    'box-header' => 'Actions',
                    'form_fields' => [
                        $this->drawHtml('small_text', 'Email', 'email', $request->old('email') , null, '', 'col-md-12 '),
                        $this->drawHtml('small_text', 'Name', 'name', $request->old('name'), null, null, 'col-md-12'),
                        $this->drawHtml('small_text', 'New Password', 'password', '' , null, '', 'col-md-12 '),
                        $this->drawHtml('small_text', 'Confirm New Password', 'password_confirmation', '', null, null, 'col-md-12'),
                    ],
                ],
                [
                    'wrapper-class' => 'col-md-8',
                    'class' => 'box-default',
                    'box-header' => 'Permissions',
                    'form_fields' => $checkboxes,

                ],
            ]
        ]);
    }

    public function store(Request $request)
    {
        $pages = request('pages');
        $pages = array_keys($pages);


        $this->validate($request,[
            'name' => '',
            'email' => 'required',
            'password' => 'required|confirmed',
        ]);

        $admin = new User();

        $admin->password = Hash::make(request('password'));

        $admin->email = request('email');
        $admin->name = request('name');
        $admin->save();


        if($pages = request('pages')){
            $admin->pages()->sync($pages);
        }

        return redirect()->route('admin.admins.index')->with('message', 'Administrator Created');
    }

    public function destroy($id)
    {
        $admin = User::find($id);
        $admin->pages()->detach();
        $admin->delete();

        return back()->with('message', 'Admin Deleted');
    }
}
