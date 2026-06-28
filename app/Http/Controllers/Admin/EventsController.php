<?php

namespace App\Http\Controllers\Admin;

use App\Event;
use App\Events\NewItem;
use App\Group;
use Carbon\Carbon;
use DataTables;
use Illuminate\Http\Request;
use App\Http\Traits\FormTrait;
use App\Http\Traits\FileTrait;
use App\Http\Controllers\Controller;
use Storage;

class EventsController extends Controller
{
    use FormTrait;
    use FileTrait;

    public function index(Request $request)
    {
        if($request->ajax()) {


            $data = Event::latest();


            return DataTables::of($data)

                ->addColumn('images', function($row){
                    return "<a href='" . route('admin.eventImages.index').'?event=' . $row->id . "' class='btn btn-primary'>Images</a>";
                })


                ->addColumn('my_image', function($row){
                    return $this->drawImage('images/events/' . $row->image, '50');
                })

                ->addColumn('name_en', function($row){
                    return $row->getTranslation('name', 'en');
                })



                ->addColumn('action', function($row){
                    return "<a class='edit-link' href='" . route('admin.events.edit', $row->id) . "'>".
                        '<i class="fa fa-edit" aria-hidden="true"></i></a>'.
                        "<a data-toggle='modal' class='delete-link' href='#deleteModal' id='" .route('admin.events.destroy', $row->id) . "'>".
                        "<i class='fa fa-trash' style='color: red;' aria-hidden='true'></i>";
                })
                //->rawColumns(['id', 'name', 'name_en', 'organized_by', 'location', 'lat', 'lng', 'from_date', 'to_date', 'my_image', 'images', 'action'])
                ->make(true);
        }


        return view('components.table_ajax')->with([
            'layout'    => 'layouts.cms',
            'pageTitle'	=> 'Events',
            'table_title' => '',
            'slug'		=> 'Faq',
            'custom_btn' => "<a href='" . route('admin.events.create') ."' class='btn btn-primary'>Add Events</a>",
            'headers'	=> ['id', 'Name', 'Organized By',  'Location', 'Latitude', 'Longitude', 'From Date', 'To Date','Thumbnail', 'Images', 'Action'],
            'action' => route('admin.events.index'),
            'columns' => json_encode([
                ['data' => 'id', 'name' => 'id'],
                ['data' =>  'name_en', 'name'=> 'name'],
                ['data' =>  'organized_by', 'name'=> 'organized_by'],
                ['data' =>  'location', 'name'=> 'location'],
                ['data' =>  'lat', 'name'=> 'lat'],
                ['data' =>  'lng', 'name'=> 'lng'],
                ['data' =>  'from_date', 'name'=> 'from_date'],
                ['data' =>  'to_date', 'name'=> 'to_date'],
                ['data' =>  'my_image', 'name'=> 'my_image', 'searchable' => false, 'sortable' => false],
                ['data' =>  'images', 'name'=> 'images', 'searchable' => false, 'sortable' => false],
                ['data' => 'action', 'name' => 'action', 'searchable' => false, 'sortable' => false],
            ]),

        ]);
    }

    public function create(Request $request)
    {

        $groups = Group::all()->pluck('name', 'group_id')->toArray();

        return view('components.form')->with([
            'layout'         => 'layouts.cms',
            'pageTitle'		=> 'Add Event',
            'method'		=> 'post',
            'form_action'	=> route('admin.events.store'),

            'boxes' => [
                [
                    'wrapper-class' => 'col-md-6',
                    'class' => 'box-default',
                    'box-header' => 'Info',
                    'form_fields' => [
                        $this->drawHtml('select-box', 'Force Language', 'strict_lang', null, $this->strictLang(), '', 'col-md-12 required'),

                        $this->drawHtml('small_text', 'Name', 'name', $request->old('name') , null, '', 'col-md-12 '),
                        $this->drawHtml('small_text', 'Name(Arabic)', 'name_ar', $request->old('name_ar') , null, '', 'col-md-12 right-to-left'),

                        $this->drawHtml('small_text', 'Organized By', 'organized_by', $request->old('organized_by'), null, '', 'col-md-12 '),

                        $this->drawHtml('text', 'Details', 'details', $request->old('details'), null, '', 'col-md-12'),
                        $this->drawHtml('text', 'Details(Arabic)', 'details_ar', $request->old('details_ar'), null, '', 'col-md-12 right-to-left'),

                        $this->drawHtml('small_text', 'Lat', 'lat', $request->old('lat'), null, '', 'col-md-6 '),
                        $this->drawHtml('small_text', 'Lng', 'lng', $request->old('lng'), null, '', 'col-md-6 '),

                        $this->drawHtml('date-time-picker', 'From Date', 'from_date', $request->old('from_date'), null, '', 'col-md-12'),
                        $this->drawHtml('date-time-picker', 'To Date', 'to_date', $request->old('to_date'), null, '', 'col-md-12'),
                        $this->drawHtml('image', 'Image', 'image', null, null, '', 'col-md-12'),
                    ],
                ],
                [
                    'wrapper-class' => 'col-md-6',
                    'class' => 'box-primary',
                    'box-header' => 'Permissions',
                    'form_fields' => [
                        $this->drawHtml('multiple-select-box', 'Groups', 'groups[]', $request->old('groups') , $groups, '', 'col-md-12 '),
                        //$this->drawHtml('checkbox', 'All Groups', 'all_groups', $request->old('all_groups') , null, '', 'col-md-12 '),

                        $this->drawHtml('checkbox', 'Send Push Notification', 'push_notification', $request->old('push_notification') , null, '', 'col-md-12 '),
                    ]
                    ],[
                    'wrapper-class' => 'col-md-6',
                    'class' => 'box-primary',
                    'box-header' => 'Push Notification',
                    'form_fields' => [
                        $this->drawHtml('small_text', 'Name', 'name_notification', $request->old('name') , null, '', 'col-md-12 '),
                        $this->drawHtml('text', 'Details', 'details_notification', $request->old('details'), null, '', 'col-md-12'),
                        $this->drawHtml('checkbox', 'Use event\'s image?', 'use_events_image', $request->old('push_notification') , null, '', 'col-md-12 '),
                        $this->drawHtml('image', 'Image', 'image_notification', null, null, '', 'col-md-12')
                    ]
                ]
            ]
        ]);
    }

    private function strictLang()
    {
        return [
            null => 'None',
            'ar' => 'Ar',
            'en' => 'En',
        ];
    }

    public function store(Request $request)
    {
        ini_set('max_execution_time', 600);
        $this->validate($request, [
            'name' => 'required_without:image|required_with:details',
            'organized_by' => 'required',
            'from_date' => 'required',
            'to_date' => 'required',
            'image' => 'required|mimes:jpeg,png,jpg,gif,svg|max:700',
            'groups' => 'required_without:all_groups',

            #notification
            'image_notification' => 'mimes:jpeg,png,jpg,gif,svg|max:700',
            'details_notification' => 'required_with:name_notification',
            'name_notification' => 'required_with:details_notification'
        ]);
        // dd($request->all());

        $event = new Event();
        $event->name = request('name');

        $event->setTranslations('name', [
            'en' => request('name'),
            'ar' => request('name_ar'),
        ]);

        $event->organized_by = request('organized_by');
        $event->strict_lang = request('strict_lang');
        $event->setTranslations('details', [
            'en' => request('details'),
            'ar' => request('details_ar'),
        ]);

        $event->from_date = Carbon::parse(request('from_date'))->toDateTimeString();
        $event->to_date = Carbon::parse(request('to_date'))->toDateTimeString();
        $event->image = $this->moveFile(request('image'), 'images/events');

        $event->lat = request('lat');
        $event->lng = request('lng');


        $event->save();

        if(request('all_groups')){
            $groups = Group::all()->pluck('group_id')->toArray();
            $request->merge(['groups' => $groups]);
        }

        //push notification
        if(request('push_notification')){
            if(request('name_notification')){
                if(!request('use_events_image')){
                    $image = $this->moveFile(request('image_notification'), 'images/notification_images');
                    $image = Storage::disk('s3')->url(env('AWS_BUCKET_PROJECT_NAME') . '/' . 'storage/' . $image);
                }
                $request->request->add([
                    'title' => request('name_notification'),
                    'text' => request('details_notification'),
                    'image' => request('use_events_image') ? Storage::disk('s3')->url(env('AWS_BUCKET_PROJECT_NAME') . '/' . 'storage/' . 'images/events/' . $event->image) : $image,
                    'image_path' => request('use_events_image') ? Storage::disk('s3')->url(env('AWS_BUCKET_PROJECT_NAME') . '/' . 'storage/' . 'images/events/' . $event->image) : $image,
                ]);
            }else{
                $request->request->add([
                    'title' => request('name_ar') ? request('name') : request('name_ar'),
                    'title_ar' => request('name_ar'),
                    'text' => request('details_ar') ? request('details_ar') : request('details'),
                    'text_ar' => request('details_ar'),
                    'image' => Storage::disk('s3')->url(env('AWS_BUCKET_PROJECT_NAME') . '/' . 'storage/' . 'images/events/' . $event->image),
                    'image_path' => Storage::disk('s3')->url(env('AWS_BUCKET_PROJECT_NAME') . '/' . 'storage/' . 'images/events/' . $event->image),
                ]);
            }

            //add the events to the request for the event
            $request->request->add(['event' => $event->id]);

            event(new NewItem($request));
        }



        $event->groups()->sync(request('groups'));

        return redirect()->route('admin.events.index')->with('message', 'Event created successfully');
    }

    public function edit($id)
    {
        $groups = Group::all()->pluck('name', 'group_id')->toArray();
        $event = Event::find($id);
        $default_groups = !$event->groups()->get()->isempty() ? $event->groups()->get()->pluck('group_id')->toArray() : '';


        return view('components.form')->with([
            'layout'         => 'layouts.cms',
            'pageTitle'		=> 'Update Event',
            'method'		=> 'update',
            'form_action'	=> route('admin.events.update', $id),

            'boxes' => [
                [
                    'wrapper-class' => 'col-md-6',
                    'class' => 'box-default',
                    'box-header' => 'Info',
                    'form_fields' => [
                        $this->drawHtml('select-box', 'Force Language', 'strict_lang', $event->strict_lang, $this->strictLang(), '', 'col-md-12 required'),

                        $this->drawHtml('small_text', 'Name', 'name', $event->getTranslation('name', 'en'), null, '', 'col-md-12 '),
                        $this->drawHtml('small_text', 'Name(Arabic)', 'name_ar', $event->getTranslation('name', 'ar') , null, '', 'col-md-12 right-to-left'),

                        $this->drawHtml('small_text', 'Organized By', 'organized_by', $event->organized_by, null, '', 'col-md-12 '),

                        $this->drawHtml('text', 'Details', 'details', $event->getTranslation('details', 'en'), null, '', 'col-md-12'),
                        $this->drawHtml('text', 'Details(Arabic)', 'details_ar', $event->getTranslation('details', 'ar'), null, '', 'col-md-12 right-to-left'),

                        $this->drawHtml('small_text', 'Lat', 'lat', $event->lat, null, '', 'col-md-6 '),
                        $this->drawHtml('small_text', 'Lng', 'lng', $event->lng, null, '', 'col-md-6 '),

                        $this->drawHtml('date-time-picker', 'From Date', 'from_date', $event->from_date, null, '', 'col-md-12'),
                        $this->drawHtml('date-time-picker', 'To Date', 'to_date', $event->to_date, null, '', 'col-md-12'),

                        $this->drawHtml('image', 'Image', 'image', null, null, '', 'col-md-12'),


                    ],
                ],
                [
                    'wrapper-class' => 'col-md-6',
                    'class' => 'box-primary',
                    'box-header' => 'Permissions',
                    'form_fields' => [
                        $this->drawHtml('multiple-select-box', 'Groups', 'groups[]', $default_groups , $groups, '', 'col-md-12 '),
                        //$this->drawHtml('checkbox', 'All Groups', 'all_groups', null , null, '', 'col-md-12 '),

                    ]
                ]

            ]
        ]);
    }

    public function update($id, Request $request)
    {
        $this->validate($request, [
            'name' => 'required_without:image|required_with:details',
            'organized_by' => 'required',
            'from_date' => 'required',
            'to_date' => 'required',
            'image' => 'required|mimes:jpeg,png,jpg,gif,svg|max:700',
            'groups' => 'required_without:all_groups'
        ]);

        $event = Event::find($id);

        $event->setTranslations('name', [
            'en' => request('name'),
            'ar' => request('name_ar'),
        ]);

        $event->organized_by = request('organized_by');
        $event->strict_lang = request('strict_lang');
        $event->setTranslations('details', [
            'en' => request('details'),
            'ar' => request('details_ar'),
        ]);

        $event->from_date = Carbon::parse(request('from_date'))->toDateTimeString();
        $event->to_date = Carbon::parse(request('to_date'))->toDateTimeString();

        $event->lat = request('lat');
        $event->lng = request('lng');

        if(request('image'))
            $event->image = $this->moveFile(request('image'), 'images/events');

        if(request('all_groups')){
            $groups = Group::all()->pluck('group_id')->toArray();
            $request->merge(['groups' => $groups]);
        }



        $event->groups()->sync(request('groups'));


        $event->save();

        return redirect()->route('admin.events.index')->with('message', 'Event Updated successfully');
    }

    public function destroy($id)
    {
        $event = Event::find($id);

        $this->removeFile($event->image);

        $event->delete();

        return back()->with('message', 'Event Deleted Successfully');
    }
}
