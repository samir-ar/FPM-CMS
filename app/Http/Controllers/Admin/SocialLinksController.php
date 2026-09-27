<?php

namespace App\Http\Controllers\Admin;

use DataTables;
use App\V2\SocialLink;
use Illuminate\Http\Request;
use App\Http\Traits\FormTrait;
use App\Http\Traits\FileTrait;
use App\Http\Controllers\Controller;

class SocialLinksController extends Controller
{
    use FormTrait;
    use FileTrait;

    /**
     * key => [label, Font Awesome 4 icon class]. Picked visually in the admin
     * via the select2 icon-picker instead of uploading a custom image, so the
     * icon shown in the app is always a real, recognizable brand mark.
     */
    private const ICON_OPTIONS = [
        'facebook' => ['Facebook', 'fa-facebook'],
        'instagram' => ['Instagram', 'fa-instagram'],
        'twitter' => ['Twitter / X', 'fa-twitter'],
        'youtube' => ['YouTube', 'fa-youtube-play'],
        'whatsapp' => ['WhatsApp', 'fa-whatsapp'],
        'linkedin' => ['LinkedIn', 'fa-linkedin'],
        'telegram' => ['Telegram', 'fa-telegram'],
        'snapchat' => ['Snapchat', 'fa-snapchat-ghost'],
        'tiktok' => ['TikTok (closest available icon)', 'fa-music'],
        'email' => ['Email', 'fa-envelope'],
        'phone' => ['Phone', 'fa-phone'],
        'website' => ['Website / Other', 'fa-globe'],
    ];

    public function index(Request $request)
    {
        if ($request->ajax()) {
            $data = SocialLink::query();

            return DataTables::of($data)
                ->addColumn('my_icon', function ($row) {
                    $faClass = self::ICON_OPTIONS[$row->icon_key][1] ?? 'fa-globe';
                    return "<i class='fa {$faClass} fa-2x'></i>";
                })
                ->addColumn('status_badge', function ($row) {
                    $badge = "<span class='label label-" . ($row->is_active ? 'success' : 'default') . "'>" .
                        ($row->is_active ? 'Active' : 'Inactive') . "</span>";
                    $toggle = "<a href='" . route('admin.social-links.toggle-active', $row->id) . "' class='btn btn-xs " . ($row->is_active ? 'btn-warning' : 'btn-success') . "' style='margin-left:8px;'>" .
                        ($row->is_active ? 'Disable' : 'Enable') . "</a>";
                    return $badge . $toggle;
                })
                ->addColumn('action', function ($row) {
                    return "<a class='edit-link' href='" . route('admin.social-links.edit', $row->id) . "'>" .
                        '<i class="fa fa-edit" aria-hidden="true"></i></a>' .
                        "<a data-toggle='modal' class='delete-link' href='#deleteModal' id='" . route('admin.social-links.destroy', $row->id) . "'>" .
                        "<i class='fa fa-trash' style='color: red;' aria-hidden='true'></i>";
                })
                ->rawColumns(['id', 'platform', 'url', 'my_icon', 'order', 'status_badge', 'action'])
                ->make(true);
        }

        return view('components.table_ajax')->with([
            'layout' => 'layouts.cms',
            'pageTitle' => 'Social Links',
            'table_title' => '',
            'slug' => 'social-link',
            'custom_btn' => "<a href='" . route('admin.social-links.create') . "' class='btn btn-primary'>Add Social Link</a>",
            'headers' => ['id', 'Platform', 'URL', 'Icon', 'Order', 'Status', 'Action'],
            'action' => route('admin.social-links.index'),
            'columns' => json_encode([
                ['data' => 'id', 'name' => 'id'],
                ['data' => 'platform', 'name' => 'platform'],
                ['data' => 'url', 'name' => 'url'],
                ['data' => 'my_icon', 'name' => 'my_icon', 'searchable' => false, 'sortable' => false],
                ['data' => 'order', 'name' => 'order'],
                ['data' => 'status_badge', 'name' => 'status_badge', 'searchable' => false, 'sortable' => false],
                ['data' => 'action', 'name' => 'action', 'searchable' => false, 'sortable' => false],
            ]),
        ]);
    }

    public function create(Request $request)
    {
        return view('components.form')->with([
            'layout' => 'layouts.cms',
            'pageTitle' => 'Add Social Link',
            'method' => 'post',
            'form_action' => route('admin.social-links.store'),

            'boxes' => [
                [
                    'wrapper-class' => 'col-md-6',
                    'class' => 'box-default',
                    'box-header' => '',
                    'form_fields' => [
                        $this->drawHtml('small_text', 'Platform (e.g. Facebook, Instagram, WhatsApp)', 'platform', $request->old('platform'), null, '', 'col-md-12 required'),
                        $this->drawHtml('small_text', 'URL', 'url', $request->old('url'), null, '', 'col-md-12 required'),
                        $this->drawHtml('icon-select', 'Icon', 'icon_key', $request->old('icon_key', 'website'), self::ICON_OPTIONS, '', 'col-md-12 required'),
                        $this->drawHtml('number', 'Order', 'order', $request->old('order'), null, '', 'col-md-12'),
                        $this->drawHtml('checkbox', 'Enabled (shows in app)', 'is_active', true, null, '', 'col-md-12'),
                    ],
                ],
            ],
        ]);
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'platform' => 'required',
            'url' => 'required',
        ]);

        $link = new SocialLink();
        $link->platform = request('platform');
        $link->url = request('url');
        $link->icon_key = request('icon_key') ?: 'website';
        $link->order = request('order') !== null && request('order') !== ''
            ? request('order')
            : (SocialLink::max('order') ?? 0) + 1;
        $link->is_active = $request->has('is_active');
        $link->save();

        return redirect()->route('admin.social-links.index')->with('message', 'Social Link Added Successfully');
    }

    public function edit($id)
    {
        $link = SocialLink::find($id);

        return view('components.form')->with([
            'layout' => 'layouts.cms',
            'pageTitle' => 'Edit Social Link',
            'method' => 'update',
            'form_action' => route('admin.social-links.update', $id),

            'boxes' => [
                [
                    'wrapper-class' => 'col-md-6',
                    'class' => 'box-default',
                    'box-header' => '',
                    'form_fields' => [
                        $this->drawHtml('small_text', 'Platform (e.g. Facebook, Instagram, WhatsApp)', 'platform', $link->platform, null, '', 'col-md-12 required'),
                        $this->drawHtml('small_text', 'URL', 'url', $link->url, null, '', 'col-md-12 required'),
                        $this->drawHtml('icon-select', 'Icon', 'icon_key', $link->icon_key ?: 'website', self::ICON_OPTIONS, '', 'col-md-12 required'),
                        $this->drawHtml('number', 'Order', 'order', $link->order, null, '', 'col-md-12'),
                        $this->drawHtml('checkbox', 'Enabled (shows in app)', 'is_active', $link->is_active, null, '', 'col-md-12'),
                    ],
                ],
            ],
        ]);
    }

    public function update($id, Request $request)
    {
        $this->validate($request, [
            'platform' => 'required',
            'url' => 'required',
        ]);

        $link = SocialLink::find($id);
        $link->platform = request('platform');
        $link->url = request('url');
        $link->icon_key = request('icon_key') ?: 'website';

        if (request('order') !== null && request('order') !== '') {
            $link->order = request('order');
        }
        $link->is_active = $request->has('is_active');
        $link->save();

        return redirect()->route('admin.social-links.index')->with('message', 'Social Link Updated Successfully');
    }

    public function destroy($id)
    {
        SocialLink::find($id)->delete();

        return back()->with('message', 'Social Link Deleted Successfully');
    }

    public function toggleActive($id)
    {
        $link = SocialLink::findOrFail($id);
        $link->is_active = !$link->is_active;
        $link->save();

        return back()->with('message', $link->is_active
            ? 'Social link enabled — now visible in the app.'
            : 'Social link disabled — hidden from the app.');
    }
}
