<?php

namespace App\Http\Controllers\Admin;

use App\Http\Traits\FileTrait;
use App\Http\Traits\FormTrait;
use Illuminate\Http\Request;

use Hash;
use Auth;
use App\User;
use App\Http\Requests;
use App\Http\Controllers\Controller;

class ProfileController extends Controller
{
    use FormTrait;
    use FileTrait;

    public function edit($id)
    {
        $admin = User::find($id);

        return view('components.form')->with([
            'layout'         => 'layouts.cms',
            'pageTitle'		=> 'Edit Profile',
            'method'		=> 'update',
            'form_action'	=> route('admin.profile.update', $admin->id),

            'boxes' => [
                [
                    'wrapper-class' => 'col-md-6',
                    'class' => 'box-default',
                    'box-header' => 'Info',
                    'form_fields' => [
                        $this->drawHtml('small_text', 'Name', 'name', $admin->name , null, 'name', 'col-md-12 '),
                        $this->drawHtml('small_text', 'Email', 'email', $admin->email , null, 'name', 'col-md-12 '),
                        $this->drawHtml('image', 'Image', 'image', $admin->avatar, null, null, 'col-md-12'),
                    ],
                ],

                [
                    'wrapper-class' => 'col-md-6',
                    'class' => 'box-default',
                    'box-header' => 'Actions',
                    'form_fields' => [
                        "<a class='btn btn-danger' href='" . route("admin.change_password_form") . "'> Change password </a>",
                        "<a class='btn btn-success' href='" . route("admin.add_user_form") . "'>Add User</a>"
                    ],
                ],
            ]
        ]);

    }

    public function update($id, Request $request)
    {

        $this->validate($request, [
            'email' => 'required',
        ]);

        $data = $request->all();

        $admin = User::find($id);

        $admin->email = $data['email'];

        if(isset($data['name']))
            $admin->name = $data['name'];

        if(isset($data['image']))
            $admin->avatar = $this->moveFile($data['image'], 'images/avatars');

        $admin->save();

        return back();

    }

    public function changePasswordForm()
    {
        $token = request('token', false);

        $admin = User::where('email_token', $token)->first();

        if(!$admin){
            return view('components.form')->with([
                'layout'         => 'layouts.cms',
                'pageTitle'		=> 'Change Password',
                'method'		=> 'post',
                'form_action'	=> route('admin.change_password'),

                'boxes' => [
                    [
                        'wrapper-class' => 'col-md-6',
                        'class' => 'box-default',
                        'box-header' => 'Content',
                        'form_fields' => [
                            $this->drawHtml('small_text', 'Current Password', 'current_password', null , null, '', 'col-md-12 '),
                            $this->drawHtml('small_text', 'New Password', 'password', '' , null, '', 'col-md-12 '),
                            $this->drawHtml('small_text', 'Confirm New Password', 'password_confirmation', '', null, null, 'col-md-12'),
                        ],
                    ],
                ]
            ]);

        }
        else{
            return view('components.form')->with([
                'layout'         => 'layouts.cms',
                'pageTitle'		=> 'Reset Passweor',
                'method'		=> 'post',
                'form_action'	=> route('admin.reset_password'),

                'boxes' => [
                    [
                        'wrapper-class' => 'col-md-6',
                        'class' => 'box-default',
                        'box-header' => '',
                        'form_fields' => [
                            $this->drawHtml('small_text', 'New Password', 'password', '' , null, '', 'col-md-12 '),
                            $this->drawHtml('small_text', 'Confirm New Password', 'password_confirmation', '', null, null, 'col-md-12'),
                        ],
                    ],
                ]
            ]);

        }

    }

    public function addUserForm()
    {

        return view('components.form')->with([
            'layout'         => 'layouts.cms',
            'pageTitle'		=> 'Add User',
            'method'		=> 'post',
            'form_action'	=> route('admin.add_user'),

            'boxes' => [
                [
                    'wrapper-class' => 'col-md-6',
                    'class' => 'box-default',
                    'box-header' => 'Info',
                    'form_fields' => [
                        $this->drawHtml('small_text', 'Name', 'name', null , null, '', 'col-md-12 '),
                        $this->drawHtml('small_text', 'Email', 'email', null , null, '', 'col-md-12 '),
                        $this->drawHtml('small_text', 'New Password', 'password', '' , null, '', 'col-md-12 '),
                        $this->drawHtml('small_text', 'Confirm New Password', 'password_confirmation', '', null, null, 'col-md-12'),
                    ],
                ],

            ]
        ]);

    }

    public function addUser(Request $request)
    {
        $this->validate($request, [
            'name' => '',
            'email' => 'required|unique:users',
            'password' => 'required|confirmed|min:6',
        ]);

        $data = $request->all();

        $admin = new User();
        $admin->email = $data['email'];

        if(isset($data['name']))
            $admin->name = $data['name'];

        $admin->password = Hash::make($data['password']);

        $admin->save();

        return back()->with('message', 'User Created');
    }

    public function resetPassword(Request $request)
    {
        $admin = Auth::guard('admin')->user();


        $this->validate($request, [
            'password' => 'required|confirmed|min:6',
        ]);


        $admin->password = Hash::make(request('password'));

        $admin->save();

        return redirect()->route('admin.logout');

    }


    public function changePassword(Request $request)
    {
        $admin = Auth::guard('admin')->user();

        if(!Hash::check($request->get('current_password'), $admin->password))
            return back()->withErrors(['password' => 'Invalid current password']);

        $this->validate($request, [
            'password' => 'required|confirmed|min:6',
        ]);


        $admin->password = Hash::make(request('password'));

        $admin->save();

        return redirect()->route('admin.logout');

    }
}
