<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckPagePermission
{
    private const RANK = ['view' => 1, 'full' => 2];

    /**
     * Usage:
     *   ->middleware('page-perm:<page_id>,view')  — needs at least View
     *   ->middleware('page-perm:<page_id>,full')  — needs Full
     *   ->middleware('page-perm:<page_id>,action:<name>') — needs at least
     *     View on the page, AND either Full or that specific named action
     *     granted (see ProfilePermission::extra_actions / User::hasExtraAction)
     *
     * Blocks the request unless the logged-in admin's Profile satisfies
     * that requirement.
     */
    public function handle(Request $request, Closure $next, $pageId, $requirement = 'view')
    {
        // Several pages share one helper route (e.g. the Party Organization pages all use the same candidate lookup):
        // 'page-perm:51|52|53,view' lets the request through when ANY of those pages satisfies the requirement.
        if (str_contains((string) $pageId, '|')) {
            $lastError = null;
            foreach (explode('|', (string) $pageId) as $one) {
                try {
                    return $this->handle($request, $next, $one, $requirement);
                } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
                    if ($e->getStatusCode() !== 403) {
                        throw $e;
                    }
                    $lastError = $e;
                }
            }
            throw $lastError;
        }

        $admin = auth('admin')->user();
        $granted = $admin?->permissionLevel($pageId);

        if (!$granted) {
            abort(403, 'You do not have permission to access this page.');
        }

        if (str_starts_with($requirement, 'action:')) {
            $action = substr($requirement, 7);
            if ($granted !== 'full' && !$admin->hasExtraAction($pageId, $action)) {
                abort(403, 'You do not have permission to perform this action.');
            }
            return $next($request);
        }

        if (self::RANK[$granted] < self::RANK[$requirement]) {
            abort(403, 'You do not have permission to access this page.');
        }

        return $next($request);
    }
}
