<?php

namespace App\Http\Controllers\Admin;

use App\Group;
use App\Setting;
use DataTables;
use App\LiveStream;
use App\Events\NewItem;
use Illuminate\Http\Request;
use App\Http\Traits\FormTrait;
use App\Http\Traits\FileTrait;
use App\Http\Controllers\Controller;
use App\Http\Repositories\SettingsRepository;
use App\Http\Repositories\LiveStreamsRepository;

class LiveStreamController extends Controller
{

    use FormTrait;
    use FileTrait;

    public $settingRepo;

    public function __construct(SettingsRepository $settingRepo, LiveStreamsRepository $liveStreamsRepo)
    {
        $this->settingRepo = $settingRepo;
        $liveStreamsRepo->initialize();
    }

    public function index(Request $request)
    {

        if($request->ajax()) {


            $data = LiveStream::query();

            return DataTables::of($data)
                ->addColumn('is_live', function($row){
                    $live_stream = Setting::where('slug', 'live_stream')->first();
                    return $live_stream->bool_flag ?
                        "<div class='bg-success text-white font-weight-bold'> LIVE </div>"
                        :
                        "<div class='bg-secondary text-white'> NOT LIVE </div>";
                })


                ->addColumn('action', function($row){
                    return "<a class='edit-link' href='" . route('admin.liveStream.edit', $row->id) . "'>".
                        '<i class="fa fa-edit" aria-hidden="true"></i></a>';
                        //"<a data-toggle='modal' class='delete-link' href='#deleteModal' id='" .route('admin.links.destroy', $row->id) . "'>".
                        //"<i class='fa fa-trash' style='color: red;' aria-hidden='true'></i>";
                })

                ->rawColumns(['id', 'is_live', 'action'])
                ->make(true);
        }


        return view('components.table_ajax')->with([
            'layout'    => 'layouts.cms',
            'pageTitle'	=> 'Live Streams',
            'table_title' => '',
            'slug'		=> 'Live Stream',
            //'custom_btn' => "<a href='" . route('admin.links.create') ."' class='btn btn-primary'>Add Link</a>",
            'headers'	=> ['id', 'Is LIVE', 'Stream', 'Updated At', 'Action'],
            'action' => route('admin.liveStream.index'),
            'columns' => json_encode([
                ['data' => 'id', 'name' => 'id'],
                ['data' =>  'is_live', 'name'=> 'my_public'],
                ['data' =>  'live_stream', 'name'=> 'live_stream'],
                ['data' =>  'updated_at', 'name'=> 'updated_at'],
                ['data' => 'action', 'name' => 'action', 'searchable' => false, 'sortable' => false],
            ]),

        ]);
    }

    public function edit($id)
    {

        $this->settingRepo->initialize();

        $has_live_stream = Setting::where('slug', 'live_stream')->first();

        $groups = Group::all()->pluck('name', 'group_id')->toArray();
        $liveStream = LiveStream::find($id);
        $default_groups = !$liveStream->groups()->get()->isempty() ? $liveStream->groups()->get()->pluck('group_id')->toArray() : '';

        return view('components.form')->with([
            'layout'         => 'layouts.cms',
            'pageTitle'		=> 'Live Stream',
            'method'		=> 'update',
            'form_action'	=> route('admin.liveStream.update', $id),

            'boxes' => [
                [
                    'wrapper-class' => 'col-md-12',
                    'class' => 'box-default',
                    'box-header' => 'Has Live Stream Settings',
                    'form_fields' => [
                        $this->drawHtml('checkbox', 'Is Stream Active', 'has_live_stream', $has_live_stream->bool_flag , null, '', 'col-md-12'),
                    ],
                ],
                [
                    'wrapper-class' => 'col-md-6',
                    'class' => 'box-primary',
                    'box-header' => 'Permissions',
                    'form_fields' => [
                        $this->drawHtml('multiple-select-box', 'Groups', 'groups[]', $default_groups , $groups, '', 'col-md-12 '),
                        // $this->drawHtml('checkbox', 'All Groups', 'is_public', $liveStream->is_public , null, '', 'col-md-12 '),

                        ]
                    ],
                    [
                    'wrapper-class' => 'col-md-6',
                    'class' => 'box-primary',
                    'box-header' => 'Notification',
                    'form_fields' => [
                        $this->drawHtml('checkbox', 'Send Notification', 'send_notification', old('send_notification') , null, '', 'col-md-12 '),
                        $this->drawHtml('small_text', 'Notification Title', 'notification_title', request()->old('notification_title') , null, '', 'col-md-12 required'),
                        $this->drawHtml('small_text', 'Notification Text', 'notification_text', request()->old('notification_text') , null, '', 'col-md-12 required'),
                    ]
                ],
                [
                    'wrapper-class' => 'col-md-6',
                    'class' => 'box-default',
                    'box-header' => 'Info',
                    'form_fields' => [
                        $this->drawHtml('small_text', 'Stream', 'live_stream', $liveStream->live_stream , null, '', 'col-md-12 required'),
                    ],
                ]

            ]
        ]);
    }

    public function update($id, Request $request)
    {
        $this->validate($request,[
            'groups'=>'required',
            'live_stream' => 'required_with:has_live_stream', // the live stream link is required the publish checkbox is set to true

            //Force the admin to always set notification when the send_notification is on
            'notification_title'=> 'required_with:send_notification',
            'notification_text'=> 'required_with:send_notification',
        ]);

        //Turn on or off the live stream
        $has_live_stream = Setting::where('slug', 'live_stream')->first();
        $has_live_stream->bool_flag = request('has_live_stream') ? true : false;
        $has_live_stream->save();

        //Set the live stream link
        $link = LiveStream::find($id);
        $link->live_stream = request('live_stream');

        //is public assign to all the members else assign it to the selected groups
        /*if(request('is_public')){
            $groups = Group::all()->pluck('group_id')->toArray();
            request()->merge(['groups' => $groups]);
        }else{
        }*/

        request()->merge(['groups' => $request->groups]);

        $link->is_public = request('is_public') ? true : false;

        $link->save();

        $link->groups()->sync(request('groups'));

        //Notification : Only send notification if send_notification is there
        if(request('send_notification')){
            $request->request->add([
                'title' => request('notification_title'),
                'title_ar' => request('notification_title'),
                'text'=> request('notification_text'),
                'text_ar'=> request('notification_text')
          ]);
           event(new NewItem(request()));
        }

        return redirect()->route('admin.liveStream.index')->with('message', 'Stream Updated successfully');
    }
}
