<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckPagePermission
{
    private const RANK = ['view' => 1, 'full' => 2];

    /**
     * Usage: ->middleware('page-perm:<page_id>,<view|full>')
     * Blocks the request unless the logged-in admin's Profile grants at
     * least the required level on that page. Stage 1 pilot only — most
     * routes in this app don't have this middleware applied yet.
     */
    public function handle(Request $request, Closure $next, $pageId, $requiredLevel = 'view')
    {
        $admin = auth('admin')->user();
        $granted = $admin?->permissionLevel($pageId);

        if (!$granted || self::RANK[$granted] < self::RANK[$requiredLevel]) {
            abort(403, 'You do not have permission to access this page.');
        }

        return $next($request);
    }
}
