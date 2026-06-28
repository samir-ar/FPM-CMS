<?php

namespace App\Http\Controllers\Admin;

use DB;
use App\Event;
use App\EventImage;
use Illuminate\Http\Request;
use App\Http\Traits\FormTrait;
use App\Http\Traits\FileTrait;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;

class EventImagesController extends Controller
{
    use FormTrait;
    use FileTrait;

    public function index(Request $request)
    {
        $query = EventImage::query();

        $query->when(request('event', false), function($q){

            return $q->whereHas('event', function($q){
                $q->where('id', request('event'));
            });
        });

        //dd('here');

        $event = Event::findOrFail(request('event'));



        return view('components.gallery')->with([
            'layout' => 'layouts.cms',
            'pageTitle'	=> 'Images for '.$event->name,
            'boxes' => [
                'gallery' => [
                    'wrapper-class' => 'col-md-8',
                    'box-header' => 'Images',
                    'class' => 'box-primary',
                    'images' => $query->get(),
                ],

                'side' => [
                    'wrapper-class' => 'col-md-4',
                    'sub' => [

                        'form' =>[
                            'action' => route('admin.eventImages.store'),
                            'form_fields' => [
                                $this->drawHtml('multiple-file-upload', 'Images', 'images',  null, ['add' => route('admin.event.upload_file'), 'delete' => route('admin.event.remove_file')],'', 'col-md-12'),
                                $this->drawHtml('hidden', '', 'event', request('event'), '', null, null)
                            ]
                        ],

                        'selected_action' => [
                            'class' => 'box-primary',
                            'actions' => [
                                [
                                    'name' => 'delete',
                                    'action' => route('admin.eventImages.multipleDelete'),
                                    'label' => 'Delete :',
                                    'button' => "<i class='fa fa-trash text-danger' aria-hidden='true'></i>",
                                ]
                            ]
                        ]

                    ],
                ]

            ]
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'event' => 'required|exists:events,id',
        ]);

        if ($validator->fails()) {

            if(request('images')){
                $images = json_decode(request('images'));

                foreach($images as $image){
                    $this->removeFile('images/events/'.$image);
                }
            }

            return back()->withErrors($validator)->withInput();
        }

        DB::transaction(function(){

            $images = json_decode(request('images'));

            foreach($images as $img){
                $image = new EventImage();
                $image->src = $img;
                $image->event_id = request('event');
                $image->save();
            }

        });

        return back()->with('message', 'Images added');
    }

    public function multipleDelete(Request $request)
    {
        $images = json_decode(request('images'));

        EventImage::whereIn('id', $images)->delete();

        return back()->with('message', 'Files Deleted Successfully');
    }

    public function uploadFile(Request $request)
    {
        $this->validate($request, [
            'myfile' => 'required|image|max:700',
        ]);

        $image = request('myfile');
        return $this->moveFile($image, 'images/events/');
    }

    public function remove_file(Request $request)
    {
        $this->removeFile('images/events/'.request('name'));
        return request('name');
    }
}
