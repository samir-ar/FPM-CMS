<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;

use App\Http\Traits\FormTrait;
use App\Http\Traits\FileTrait;
use App\Http\Traits\BirthdayWishesTrait;

use Carbon\Carbon;
use App\V2\NewsImage;
use App\Events\NewItem;
use App\V2\Group;
use App\Http\Repositories\V2\ApiRepository;
use App\V2\Notification;
use App\V2\NewsAttachement;
use DataTables;
use Storage;

use App\Http\Controllers\Controller;

class NotificationController extends Controller
{
    use FormTrait;
    use FileTrait;
    use BirthdayWishesTrait;


        public function index(Request $request)
        {
            if($request->ajax()) {

                $data = Notification::query();
                return DataTables::of($data)

                    ->addColumn('image', function($row){
                        return $this->drawImage('images/notifications_images/' . $row->image, '100');
                    })

                  /*   ->addColumn('action', function($row){
                        return "<a class='edit-link' href='" . route('admin.news.edit', $row->id) . "'>".
                            '<i class="fa fa-edit" aria-hidden="true"></i></a>'.
                            "<a data-toggle='modal' class='delete-link' href='#deleteModal' id='" .route('admin.news.destroy', $row->id) . "'>".
                            "<i class='fa fa-trash' style='color: red;' aria-hidden='true'></i>";
                    }) */

                    ->addColumn('title', function($row){
                        return $row->title;
                    })

                    ->addColumn('text', function($row){
                        return (strlen($row->text) <= 100)? $row->text : mb_substr($row->text,0,100,'utf-8')."...";
                    })
                    ->rawColumns(['id', 'title','text', 'image'])
                    ->make(true);
            }


            return view('components.table_ajax')->with([
                'layout'    => 'layouts.cms',
                'pageTitle'	=> 'Push Notification',
                'table_title' => '',
                'slug'		=> 'push-notification',
                'custom_btn' => "<a href='" . route('admin.bulkpushnotification.create') ."' class='btn btn-primary'>Send Notification</a> " .
                    "<a href='" . route('admin.birthdaywishes.send') . "' class='btn btn-default' onclick=\"return confirm('Send birthday wishes to every member whose birthday is today?')\">Send Today's Birthday Wishes</a>",
                'headers'	=> ['id', 'Title', "Text", 'Images'],
                'action' => route('admin.bulkpushnotification.index'),
                'columns' => json_encode([
                    ['data' => 'id', 'name' => 'id'],
                    ['data' =>  'title', 'name'=> 'title'],
                    ['data' =>  'text', 'name'=> 'text'],
                    ['data' =>  'image', 'name'=> 'image'],
                ]),
            ]);
    }


    public function create(Request $request){

        $groups = Group::all()->pluck('name', 'group_id')->toArray();

        return view('components.form')->with([
            'layout'         => 'layouts.cms',
            'pageTitle'		=> 'Send Notification',
            'method'		=> 'post',
            'form_action'	=> route('admin.bulkpushnotification.store'),

            'boxes' => [
                [
                    'wrapper-class' => 'col-md-12',
                    'class' => 'box-default',
                    'box-header' => 'Info',
                    'form_fields' => [
                        $this->drawHtml('small_text', 'Title', 'title', $request->old('title') , null, '', 'col-md-12 required'),
                        $this->drawHtml('small_text', 'Text', 'text', $request->old('text') , null, '', 'col-md-12 required'),

                        //$this->drawHtml("select-box","Type","type",null,["Memo","News","Event","Poll"],"", 'col-md-12 required'),
                        $this->drawHtml('image', 'Image', 'image', null, null, '', 'col-md-12'),

                        //$this->drawHtml('multiple-file-upload', 'UploadImage', 'image', null, ['add' => route('admin.notificationimage.upload'), 'delete' => route('admin.notificationimage.delete'), 'default' => null] , '', 'col-md-12'),

                        $this->drawHtml('multiple-select-box', 'Groups (leave empty to send to all members)', 'groups[]', $request->old('groups'), $groups, '', 'col-md-12'),
                        $this->drawHtml('small_text', 'Member IDs (optional, comma-separated — if filled, sends only to these members and skips Groups entirely)', 'member_ids', $request->old('member_ids'), null, '', 'col-md-12'),
                    ],
                ]

            ]
        ]);
    }


    public function sendBirthdayWishes(Request $request){
        $count = $this->sendTodaysBirthdayWishes();

        return back()->with('message', "Sent birthday wishes to {$count} member(s).");
    }


    public function deleteImage(Request $request){
        $name = request('name');
        $this->removeFile('images/notification_images'.$name);
        return $name;
    }


    public function imageUpload(Request $request){
        $this->validate($request, [
            'myfile' => 'required|max:700',
        ]);

        $image = $request->file('myfile');
        return $this->moveFile($image, 'images/notification_images');
    }


    public function store(Request $request){
        $this->validate($request, [
            'title' => 'required',
            'text' => 'required',
            'image' => 'mimes:jpeg,png,jpg,gif,svg|max:4000',
        ]);

        $image = null;

        if(request("image")){
           $image =  $this->moveFile($image, 'images/notification_images');
        }


        //push notification
        $request->request->add([
            'title' => request('title'),
            'title_ar' => request('title'),
            'text' => request('text'),
            'text_ar' => request('text'),
            'image' => ($image)? Storage::disk('s3')->url(config('app.aws_bucket_project_name') . '/storage/' . $image) : null,
            'image_path' => ($image)? Storage::disk('s3')->url(config('app.aws_bucket_project_name') . '/storage/' . $image) : null
        ]);


        //If specific member IDs were entered, they take priority and skip the
        //Groups path entirely (the external TWH groups-members API has been
        //observed to intermittently return empty for large group counts).
        if (request('member_ids')) {
            $memberIds = array_values(array_filter(array_map('trim', explode(',', request('member_ids')))));
            $request->merge(['member_ids' => $memberIds, 'groups' => []]);
        } else {
            //If specific groups were selected, send only to them; otherwise default to all members
            if (request('groups')) {
                $groups = request('groups');
            } else {
                $groups = Group::all()->pluck('group_id')->toArray();
            }
            $request->merge(['groups' => $groups, 'member_ids' => []]);
        }

        //add the news to the request for the event
        //$request->request->add(['news' => $news->id]);

        event(new NewItem($request));

        return redirect()->route('admin.bulkpushnotification.index')->with('message', 'All the users have been notified.');
    }
}
