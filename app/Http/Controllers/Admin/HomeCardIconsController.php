<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Traits\FileTrait;
use App\V2\HomeCardIcon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

// "Home card icons": the round icon on each Home card, chosen per card type (News / Events / Polls). Upload an image per
// type (square, e.g. 200x200); with none the app keeps its default (a news item's own logo, else the FPM logo).
class HomeCardIconsController extends Controller
{
    use FileTrait;

    public function index()
    {
        $rows = [];
        foreach (HomeCardIcon::TYPES as $type => $label) {
            $rows[$type] = ['label' => $label, 'row' => HomeCardIcon::row($type)];
        }

        return view('cms.home_card_icons.index')->with(['layout' => 'layouts.cms', 'rows' => $rows]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'icon.*' => 'nullable|image|mimes:png,jpg,jpeg,webp|max:2048',
            'remove.*' => 'nullable|boolean',
            'use_for_all_news' => 'nullable|boolean',
        ]);

        foreach (array_keys(HomeCardIcon::TYPES) as $type) {
            $row = HomeCardIcon::firstOrNew(['type' => $type]);

            if ($request->hasFile("icon.$type")) {
                $row->image = $this->moveFile($request->file("icon.$type"), HomeCardIcon::DIR);
            } elseif ($request->input("remove.$type")) {
                $row->image = null;
            }

            if ($type === 'NEWS') {
                $row->use_for_all = (bool) $request->input('use_for_all_news');
            }

            $row->save();
        }

        HomeCardIcon::forget();
        Log::info('home_card_icons_updated', ['admin_id' => optional($request->user())->id]);

        return redirect()->route('admin.home-card-icons.index')->with('message', 'Home card icons saved. The app shows them the next time the Home feed loads.');
    }
}
