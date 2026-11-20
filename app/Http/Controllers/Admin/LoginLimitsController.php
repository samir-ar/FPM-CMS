<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\LoginLimits;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

// "Login limits": see and reset the app-login counters (code requests, wrong codes) for ONE member or for EVERYBODY.
// Resetting never touches accounts, votes or tokens: it only makes the member (or everybody) able to ask for codes
// and type codes again. Every reset is written to the log with the admin who did it.
class LoginLimitsController extends Controller
{
    public function index(Request $request)
    {
        $member = ltrim(trim((string) $request->query('member_id', '')), '0');
        $status = null;
        $name = null;
        if ($member !== '') {
            $status = LoginLimits::status($member);
            $name = \DB::table('fpm_users')->where('MemberId', $member)->value('UserFullName');
        }

        return view('cms.login_limits.index')->with([
            'layout' => 'layouts.cms',
            'member' => $member,
            'status' => $status,
            'name' => $name,
            'recent' => LoginLimits::recentMembers(),
        ]);
    }

    public function resetMember(Request $request)
    {
        $request->validate(['member_id' => 'required|regex:/^[0-9]+$/|max:20']);
        $member = ltrim($request->input('member_id'), '0');

        LoginLimits::resetMember($member);
        Log::info('login_limits_reset_member', ['member_id' => $member, 'admin_id' => optional($request->user())->id, 'ip' => $request->ip()]);

        return redirect()->route('admin.users.login-limits', ['member_id' => $member])
            ->with('message', "Login limits reset for member {$member}. They can ask for codes and log in again.");
    }

    public function resetAll(Request $request)
    {
        LoginLimits::resetAll();
        Log::info('login_limits_reset_all', ['admin_id' => optional($request->user())->id, 'ip' => $request->ip()]);

        return redirect()->route('admin.users.login-limits')
            ->with('message', 'Login limits reset for EVERYBODY (code requests, wrong codes and the per-IP counters).');
    }
}
