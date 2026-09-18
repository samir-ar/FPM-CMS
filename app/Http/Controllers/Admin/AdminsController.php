<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;

use Hash;
use App\Page;
use App\User;
use App\V2\Profile;
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

    public function edit($id)
    {
        $admin = User::find($id);

        return view('components.form')->with([
            'layout'         => 'layouts.cms',
            'pageTitle'		=> 'Update Admin',
            'method'		=> 'update',
            'form_action'	=> route('admin.admins.update', $id),

            'boxes' => [
                [
                    'wrapper-class' => 'col-md-6',
                    'class' => 'box-default',
                    'box-header' => 'Actions',
                    'form_fields' => [
                        $this->drawHtml('small_text', 'Email', 'email', $admin->email , null, '', 'col-md-12 '),
                        $this->drawHtml('small_text', 'Name', 'name', $admin->name, null, null, 'col-md-12'),
                        $this->drawHtml('small_text', 'New Password', 'password', '' , null, '', 'col-md-12 '),
                        $this->drawHtml('small_text', 'Confirm New Password', 'password_confirmation', '', null, null, 'col-md-12'),
                        // Not assigning one leaves this admin fully blocked
                        // from every page-perm-protected route until a
                        // profile is set — deliberate, secure-by-default.
                        $this->drawHtml('select-box', 'Profile', 'profile_id', $admin->profile_id,
                            ['' => '— No profile (no access) —'] + Profile::pluck('name', 'id')->all(),
                            null, 'col-md-12'),
                    ],
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
        $admin->profile_id = request('profile_id') ?: null;

        $admin->save();

        return redirect()->route('admin.admins.index')->with('message', 'Administrator updated');
    }

    public function create(Request $request)
    {
        return view('components.form')->with([
            'layout'         => 'layouts.cms',
            'pageTitle'		=> 'Add Admin',
            'method'		=> 'post',
            'form_action'	=> route('admin.admins.store'),

            'boxes' => [
                [
                    'wrapper-class' => 'col-md-6',
                    'class' => 'box-default',
                    'box-header' => 'Actions',
                    'form_fields' => [
                        $this->drawHtml('small_text', 'Email', 'email', $request->old('email') , null, '', 'col-md-12 '),
                        $this->drawHtml('small_text', 'Name', 'name', $request->old('name'), null, null, 'col-md-12'),
                        $this->drawHtml('small_text', 'New Password', 'password', '' , null, '', 'col-md-12 '),
                        $this->drawHtml('small_text', 'Confirm New Password', 'password_confirmation', '', null, null, 'col-md-12'),
                        $this->drawHtml('select-box', 'Profile', 'profile_id', $request->old('profile_id'),
                            ['' => '— No profile (no access) —'] + Profile::pluck('name', 'id')->all(),
                            null, 'col-md-12'),
                    ],
                ],
            ]
        ]);
    }

    public function store(Request $request)
    {
        $this->validate($request,[
            'name' => '',
            'email' => 'required',
            'password' => 'required|confirmed',
        ]);

        $admin = new User();

        $admin->password = Hash::make(request('password'));

        $admin->email = request('email');
        $admin->name = request('name');
        $admin->profile_id = request('profile_id') ?: null;
        $admin->save();

        return redirect()->route('admin.admins.index')->with('message', 'Administrator Created');
    }

    public function destroy($id)
    {
        User::find($id)->delete();

        return back()->with('message', 'Admin Deleted');
    }
}
