<?php

namespace App\Http\Middleware;

use App\V2\ProfilePermission;
use Closure;
use Illuminate\Http\Request;

/**
 * For the shared helper endpoints (rich-text-editor image upload/delete) that
 * are called from many unrelated admin forms and so have no single page to be
 * tied to: allow only admins whose Profile gives them Full control of at least
 * one page, i.e. someone who can legitimately edit content. A View-only admin,
 * or an admin with no Profile, can no longer upload or delete files.
 */
class RequireAnyFullPermission
{
    public function handle(Request $request, Closure $next)
    {
        $admin = auth('admin')->user();

        $allowed = $admin && $admin->profile_id
            && ProfilePermission::where('profile_id', $admin->profile_id)->where('level', 'full')->exists();

        if (!$allowed) {
            abort(403, 'You do not have permission to upload or delete files.');
        }

        return $next($request);
    }
}
