<?php

namespace App\Http\Controllers\Admin;

use DataTables;
use App\Volunteer;
use App\V2\VolunteerField;
use App\Events\NewItem;
use Illuminate\Http\Request;
use App\Http\Traits\FormTrait;
use App\Http\Traits\FileTrait;
use App\Http\Controllers\Controller;

class VolunteersController extends Controller
{
    use FormTrait;
    use FileTrait;

    const LANGUAGE_OPTIONS = [
        'ar' => 'Arabic',
        'en' => 'English',
    ];

    public function index(Request $request)
    {

        if($request->ajax()) {


            $data = Volunteer::latest();


            return DataTables::of($data)


                ->addColumn('my_image', function($row){
                    return $this->drawImage('images/volunteers/' . $row->image, '50');
                })

                ->addColumn('title_en', function($row){
                    return $row->getTranslation('title', 'en');
                })

                ->addColumn('users', function($row){
                    return "<a href='" . route('admin.volunteerUsers.index').'?volunteer_id='. $row->id ."'>Users</a>";
                })

                ->addColumn('status_badge', function($row){
                    $badge = "<span class='label label-" . ($row->is_active ? 'success' : 'default') . "'>" .
                        ($row->is_active ? 'Active' : 'Inactive') . "</span>";
                    $toggle = "<a href='" . route('admin.volunteers.toggle-active', $row->id) . "' class='btn btn-xs " . ($row->is_active ? 'btn-warning' : 'btn-success') . "' style='margin-left:8px;'>" .
                        ($row->is_active ? 'Disable' : 'Enable') . "</a>";
                    return $badge . $toggle;
                })

                ->addColumn('action', function($row){
                    return "<a class='edit-link' href='" . route('admin.volunteers.edit', $row->id) . "'>".
                        '<i class="fa fa-edit" aria-hidden="true"></i></a>'.
                        "<a href='" . route('admin.volunteers.send-notification.form', $row->id) . "' title='Send Notification'>".
                        '<i class="fa fa-bell" aria-hidden="true"></i></a>'.
                        "<a data-toggle='modal' class='delete-link' href='#deleteModal' id='" .route('admin.volunteers.destroy', $row->id) . "'>".
                        "<i class='fa fa-trash' style='color: red;' aria-hidden='true'></i>";
                })
                ->rawColumns(['id', 'title_en', 'my_image', 'users', 'status_badge', 'action'])
                ->make(true);
        }


        return view('components.table_ajax')->with([
            'layout'    => 'layouts.cms',
            'pageTitle'	=> 'Volunteers',
            'table_title' => '',
            'slug'		=> 'volunteer',
            'custom_btn' => "<a href='" . route('admin.volunteers.create') ."' class='btn btn-primary'>Add Volunteer</a>",
            'headers'	=> ['id', 'Name', 'Image',  'Users', 'Status', 'Action'],
            'action' => route('admin.volunteers.index'),
            'columns' => json_encode([
                ['data' => 'id', 'name' => 'id'],
                ['data' =>  'title_en', 'name'=> 'title'],
                ['data' =>  'my_image', 'name'=> 'my_image', 'searchable' => false, 'sortable' => false],
                ['data' =>  'users', 'name'=> 'users'],
                ['data' =>  'status_badge', 'name'=> 'status_badge', 'searchable' => false, 'sortable' => false],
                ['data' => 'action', 'name' => 'action', 'searchable' => false, 'sortable' => false],
            ]),

        ]);
    }

    public function create(Request $request)
    {
        $groups = \App\Group::all()->pluck('name', 'group_id')->toArray();

        return view('components.form')->with([
            'layout'         => 'layouts.cms',
            'pageTitle'		=> 'Add Volunteer',
            'method'		=> 'post',
            'form_action'	=> route('admin.volunteers.store'),

            'boxes' => [
                [
                    'wrapper-class' => 'col-md-6',
                    'class' => 'box-default',
                    'box-header' => 'Info',
                    'form_fields' => [
                        $this->drawHtml('small_text', 'Title', 'title', $request->old('title') , null, '', 'col-md-12 required'),
                        $this->drawHtml('small_text', 'Title(Arabic)', 'title_ar', $request->old('title_ar') , null, '', 'col-md-12 right-to-left required'),


                        $this->drawHtml('text', 'Details', 'text', $request->old('text'), null, '', 'col-md-12 no-ck required'),
                        $this->drawHtml('text', 'Details(Arabic)', 'text_ar', $request->old('text_ar'), null, '', 'col-md-12 no-ck right-to-left required'),


                        $this->drawHtml('image', 'Image', 'image', null, null, '', 'col-md-12 required'),

                        $this->drawHtml('date-time-picker', 'Event Date', 'event_date', $request->old('event_date'), null, '', 'col-md-12'),

                        $this->drawHtml('checkbox', 'Enabled (shows in app)', 'is_active', $request->old('is_active', true), null, '', 'col-md-12'),

                        $this->drawHtml('select-box', 'Display Language (in the app)', 'display_language', $request->old('display_language', 'ar'), self::LANGUAGE_OPTIONS, '', 'col-md-12'),

                        $this->drawHtml('multi_option', 'Fields needed (e.g. Medical, Logistics, Registration)', 'field', null, null, 'Field', 'col-md-12'),

                        $this->drawHtml('checkbox', 'Send Push Notification', 'push_notification', $request->old('push_notification'), null, '', 'col-md-12'),

                        $this->drawHtml('select-box', 'Notification Language', 'push_language', $request->old('push_language', 'ar'), self::LANGUAGE_OPTIONS, '', 'col-md-12'),

                        $this->drawHtml('multiple-select-box', 'Notify Groups (leave empty to send to all members)', 'groups[]', $request->old('groups'), $groups, '', 'col-md-12'),

                    ],
                ],

            ]
        ]);
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'image' => 'nullable|image:max:700',
        ]);

        $volunteer = new Volunteer();
        $volunteer->setTranslations('title', [
            'en' => request('title'),
            'ar' => request('title_ar'),
        ]);

        $volunteer->setTranslations('text', [
            'en' => request('text'),
            'ar' => request('text_ar'),
        ]);

        if(request('image'))
            $volunteer->image = $this->moveFile(request('image'), 'images/volunteers');

        $volunteer->event_date = request('event_date') ? \Carbon\Carbon::parse(request('event_date'))->toDateTimeString() : null;
        $volunteer->display_language = request('display_language', 'ar');
        $volunteer->is_active = request()->has('is_active');

        $volunteer->save();

        foreach (request('options', []) as $o) {
            if (empty($o['name']) && empty($o['name_ar'])) {
                continue;
            }
            $field = new VolunteerField();
            $field->volunteer_id = $volunteer->id;
            $field->setTranslations('name', [
                'en' => $o['name'] ?? '',
                'ar' => $o['name_ar'] ?? '',
            ]);
            $field->save();
        }

        if (request('push_notification')) {
            $request->request->add([
                'title' => request('title'),
                'title_ar' => request('title_ar'),
                'text' => request('text'),
                'text_ar' => request('text_ar'),
                'image_path' => $volunteer->image
                    ? \Storage::disk('s3')->url(config('app.aws_bucket_project_name') . '/' . 'storage/' . 'images/volunteers/' . $volunteer->image)
                    : null,
                'event_date' => $volunteer->event_date,
                'notification_language' => request('push_language', 'ar'),
            ]);

            $groups = request('groups') ? request('groups') : \App\Group::all()->pluck('group_id')->toArray();
            $request->merge(['groups' => $groups]);

            event(new NewItem($request));
        }

        return redirect()->route('admin.volunteers.index')->with('message', 'Volunteer created');
    }

    public function edit($id)
    {
        $volunteer = Volunteer::find($id);

        $default_fields = [];
        foreach ($volunteer->fields as $field) {
            $default_fields[] = [
                'id' => $field->id,
                'name' => $field->getTranslation('name', 'en'),
                'name_ar' => $field->getTranslation('name', 'ar'),
            ];
        }

        return view('components.form')->with([
            'layout'         => 'layouts.cms',
            'pageTitle'		=> 'Add Volunteer',
            'method'		=> 'update',
            'form_action'	=> route('admin.volunteers.update', $id),

            'boxes' => [
                [
                    'wrapper-class' => 'col-md-6',
                    'class' => 'box-default',
                    'box-header' => 'Info',
                    'form_fields' => [
                        $this->drawHtml('small_text', 'Title', 'title', $volunteer->getTranslation('title', 'en') , null, '', 'col-md-12 required'),
                        $this->drawHtml('small_text', 'Title(Arabic)', 'title_ar', $volunteer->getTranslation('title', 'ar') , null, '', 'col-md-12 right-to-left required'),

                        $this->drawHtml('text', 'Details', 'text', $volunteer->getTranslation('text', 'en'), null, '', 'col-md-12 no-ck required'),
                        $this->drawHtml('text', 'Details(Arabic)', 'text_ar', $volunteer->getTranslation('text', 'ar'), null, '', 'col-md-12 no-ck right-to-left required'),

                        $this->drawHtml('image', 'Image', 'image', $volunteer->image, null, '', 'col-md-12'),

                        $this->drawHtml('date-time-picker', 'Event Date', 'event_date', $volunteer->event_date, null, '', 'col-md-12'),

                        $this->drawHtml('checkbox', 'Enabled (shows in app)', 'is_active', $volunteer->is_active, null, '', 'col-md-12'),

                        $this->drawHtml('select-box', 'Display Language (in the app)', 'display_language', $volunteer->display_language, self::LANGUAGE_OPTIONS, '', 'col-md-12'),

                        $this->drawHtml('multi_option', 'Fields needed (e.g. Medical, Logistics, Registration)', 'field', $default_fields, null, 'Field', 'col-md-12'),

                    ],
                ],

            ]
        ]);
    }

    public function update($id, Request $request)
    {
        $volunteer = Volunteer::find($id);
        $volunteer->setTranslations('title', [
            'en' => request('title'),
            'ar' => request('title_ar'),
        ]);

        $volunteer->setTranslations('text', [
            'en' => request('text'),
            'ar' => request('text_ar'),
        ]);

        if(request('image')){
            $this->removeFile($volunteer->image);
            $volunteer->image = $this->moveFile(request('image'), 'images/volunteers');

        }

        $volunteer->event_date = request('event_date') ? \Carbon\Carbon::parse(request('event_date'))->toDateTimeString() : null;
        $volunteer->display_language = request('display_language', 'ar');
        $volunteer->is_active = request()->has('is_active');

        $volunteer->save();

        // Remove fields that were deleted client-side, update the ones that remain, add any new ones.
        $submittedIds = collect(request('options', []))->pluck('id')->filter()->toArray();
        $volunteer->fields()->whereNotIn('id', $submittedIds)->delete();

        foreach (request('options', []) as $o) {
            if (empty($o['name']) && empty($o['name_ar'])) {
                continue;
            }
            $field = !empty($o['id']) ? VolunteerField::find($o['id']) : new VolunteerField();
            if (!$field) {
                continue;
            }
            $field->volunteer_id = $volunteer->id;
            $field->setTranslations('name', [
                'en' => $o['name'] ?? '',
                'ar' => $o['name_ar'] ?? '',
            ]);
            $field->save();
        }

        return redirect()->route('admin.volunteers.index')->with('message', 'Volunteer updated');
    }

    public function destroy($id)
    {
        $volunteer = Volunteer::find($id);
        $this->removeFile($volunteer->image);

        $volunteer->delete();

        return back()->with('message', 'Volunteer deleted');
    }

    public function toggleActive($id)
    {
        $volunteer = Volunteer::findOrFail($id);
        $volunteer->is_active = !$volunteer->is_active;
        $volunteer->save();

        return back()->with('message', $volunteer->is_active
            ? 'Volunteer opportunity enabled — now visible in the app.'
            : 'Volunteer opportunity disabled — hidden from the app.');
    }

    public function sendNotificationForm($id)
    {
        $volunteer = Volunteer::find($id);
        $groups = \App\Group::all()->pluck('name', 'group_id')->toArray();

        return view('components.form')->with([
            'layout'         => 'layouts.cms',
            'pageTitle'		=> 'Send Notification: ' . $volunteer->getTranslation('title', 'en'),
            'method'		=> 'post',
            'form_action'	=> route('admin.volunteers.send-notification', $id),

            'boxes' => [
                [
                    'wrapper-class' => 'col-md-6',
                    'class' => 'box-default',
                    'box-header' => 'Send Push Notification',
                    'form_fields' => [
                        $this->drawHtml('select-box', 'Notification Language', 'push_language', 'ar', self::LANGUAGE_OPTIONS, '', 'col-md-12'),
                        $this->drawHtml('multiple-select-box', 'Notify Groups (leave empty to send to all members)', 'groups[]', null, $groups, '', 'col-md-12'),
                    ],
                ],
            ]
        ]);
    }

    public function sendNotification($id, Request $request)
    {
        $volunteer = Volunteer::find($id);

        $request->request->add([
            'title' => $volunteer->getTranslation('title', 'en'),
            'title_ar' => $volunteer->getTranslation('title', 'ar'),
            'text' => $volunteer->getTranslation('text', 'en'),
            'text_ar' => $volunteer->getTranslation('text', 'ar'),
            'image_path' => $volunteer->image
                ? \Storage::disk('s3')->url(config('app.aws_bucket_project_name') . '/' . 'storage/' . 'images/volunteers/' . $volunteer->image)
                : null,
            'event_date' => $volunteer->event_date,
            'notification_language' => request('push_language', 'ar'),
        ]);

        $groups = request('groups') ? request('groups') : \App\Group::all()->pluck('group_id')->toArray();
        $request->merge(['groups' => $groups]);

        event(new NewItem($request));

        return redirect()->route('admin.volunteers.index')->with('message', 'Notification sent');
    }
}
