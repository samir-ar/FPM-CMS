<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Analytics\AppAnalyticsDashboardService;

class StoreAnalyticsController extends Controller
{
    public function index(AppAnalyticsDashboardService $service)
    {
        $summary = $service->summary();

        return view('cms.store_analytics.index', compact('summary'))
            ->with('pageTitle', 'Store Analytics');
    }
}
