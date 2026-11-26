<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Auth\AuthenticatesUsers;


class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = '/admin';

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest:admin')->except('logout');
    }

    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        // the "email" field holds the account's sign-in name: an e-mail address or a plain username
        $this->validate($request, [
            'email'     => 'required|string|max:191',
            'password'  => 'required|min:6'
        ]);

        if (Auth::guard('admin')->attempt(['email' => trim($request->email), 'password' => $request->password], $request->get('remember'))) {
            $admin = Auth::guard('admin')->user();

            // Profiles without Dashboard access (page id 0) shouldn't land
            // there just because it's the default - send them to the first
            // page they actually have access to instead. Still honors a
            // real "intended" URL (e.g. they hit a protected link while
            // logged out) over this fallback.
            $default = $admin->hasPage(0) ? '/admin' : ($admin->firstAccessibleUrl() ?? '/admin');

            return redirect()->intended($default);
        }
        return back()->withInput($request->only('email', 'remember'));
    }


    public function logout(Request $request)
    {
        Auth::guard('admin')->logout();
        return redirect('/login');
    }
}


