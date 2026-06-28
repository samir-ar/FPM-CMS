<?php

namespace App\Http\Controllers\Admin;

use Carbon\Carbon;
use App\V2\NewsImage;
use App\Events\NewItem;
use App\V2\Group;
use App\V2\Placeholder;
use App\Http\Repositories\V2\ApiRepository;
use App\V2\News;
use App\V2\NewsAttachement;
use DataTables;
use Illuminate\Http\Request;
use App\Http\Traits\FormTrait;
use App\Http\Traits\FileTrait;
use App\Http\Controllers\Controller;
use Storage;

class NewsController extends Controller
{
    use FormTrait;
    use FileTrait;

    public function index(Request $request)
    {
        if ($request->ajax()) {

            $data = News::query();
            return DataTables::of($data)

                ->addColumn('my_image', function ($row) {
                    return $this->drawImage('images/news/' . $row->source_image, '100');
                })

                ->addColumn('action', function ($row) {
                    return "<a class='edit-link' href='" . route('admin.news.edit', $row->id) . "'>" .
                        '<i class="fa fa-edit" aria-hidden="true"></i></a>' .
                        "<a data-toggle='modal' class='delete-link' href='#deleteModal' id='" . route('admin.news.destroy', $row->id) . "'>" .
                        "<i class='fa fa-trash' style='color: red;' aria-hidden='true'></i>";
                })

                ->addColumn('title_en', function ($row) {
                    return $row->getTranslation('title', 'en');
                })
                ->addColumn('source_en', function ($row) {
                    return $row->getTranslation('source', 'en');
                })
                ->rawColumns(['id', 'title', 'title_en', 'source', 'source_en', 'my_image', 'date', 'action'])
                ->make(true);
        }


        return view('components.table_ajax')->with([
            'layout'    => 'layouts.cms',
            'pageTitle'    => 'News',
            'table_title' => '',
            'slug'        => 'news',
            'custom_btn' => "<a href='" . route('admin.news.create') . "' class='btn btn-primary'>Add News</a>",
            'headers'    => ['id', 'Title', 'Source',  'Images', 'Date', 'Action'],
            'action' => route('admin.news.index'),
            'columns' => json_encode([
                ['data' => 'id', 'name' => 'id'],
                ['data' =>  'title_en', 'name' => 'title'],
                ['data' =>  'source_en', 'name' => 'source_en'],
                ['data' =>  'my_image', 'name' => 'my_image', 'searchable' => false, 'sortable' => false],
                ['data' =>  'date', 'name' => 'date'],
                ['data' => 'action', 'name' => 'action', 'searchable' => false, 'sortable' => false],
            ]),
        ]);
    }


    public function create(Request $request)
    {
        $groups = Group::all()->pluck('name', 'group_id')->toArray();

        return view('components.form')->with([
            'layout'         => 'layouts.cms',
            'pageTitle'        => 'Add News',
            'method'        => 'post',
            'form_action'    => route('admin.news.store'),

            'boxes' => [
                [
                    'wrapper-class' => 'col-md-12',
                    'class' => 'box-default',
                    'box-header' => 'Info',
                    'form_fields' => [
                        $this->drawHtml('select-box', 'Force Language', 'strict_lang', null, $this->strictLang(), '', 'col-md-12 required'),

                        $this->drawHtml('small_text', 'Title', 'title', $request->old('title'), null, '', 'col-md-6 required'),
                        $this->drawHtml('small_text', 'Title(Arabic)', 'title_ar', $request->old('title_ar'), null, '', 'col-md-6 right-to-left required'),

                        $this->drawHtml('small_text', 'Source', 'source', $request->old('source'), null, '', 'col-md-6 '),
                        $this->drawHtml('small_text', 'Source(Arabic)', 'source_ar', $request->old('source_ar'), null, '', 'col-md-6 right-to-left'),

                        #Upload multiple pdf/attachements
                        // NOT WORKING $this->drawHtml('multiple-file-upload', 'Attach file', 'attachement', null, ['add' => route('admin.newsattachement.upload'), 'delete' => route('admin.newsattachement.delete'), 'default' => null] , '', 'col-md-12'),


                        // OLD WAY SINGLE IMAGE $this->drawHtml('image', 'Source Image', 'source_image', null, null, '', 'col-md-12'),
                        #new way of uploading image

                        $this->drawHtml('text', 'Details', 'details', $request->old('details'), null, '', 'col-md-6 required'),
                        $this->drawHtml('text', 'Details(Arabic)', 'details_ar', $request->old('details_ar'), null, '', 'col-md-6 right-to-left required'),

                        $this->drawHtml('date-time-picker', 'Date', 'date', $request->old('date'), null, '', 'col-md-12 required'),
                    ],
                ],
                [
                    'wrapper-class' => 'col-md-6',
                    'class' => 'box-primary',
                    'box-header' => 'Media',

                    'form_fields' => [
                        $this->drawHtml('multiple-file-upload', 'Upload Images', 'images', null, ['add' => route('admin.newsimage.upload'), 'delete' => route('admin.newsimage.delete'), 'default' => null], '', 'col-md-12'),
                        $this->drawHtml('file', 'Source Image', 'source_image', null, 'image/*', '', 'col-md-12'),
                        $this->drawHtml('file', 'Video Thumbnail', 'thumbnail', null, 'image/*', '', 'col-md-12'),
                        $this->drawHtml('file', 'Video', 'video', null, 'video/mp4', '', 'col-md-12'),
                        $this->drawHtml('pdf', "PDF", "attachment", null, 'application/pdf', null, "form-group col-md-12"),
                    ]
                ],
                [
                    'wrapper-class' => 'col-md-6',
                    'class' => 'box-primary',
                    'box-header' => 'Permissions',
                    'form_fields' => [
                        $this->drawHtml('multiple-select-box', 'Groups', 'groups[]', $request->old('groups'), $groups, '', 'col-md-12 '),
                        //$this->drawHtml('checkbox', 'All Groups', 'all_groups', $request->old('all_groups') , null, '', 'col-md-12 '),
                        $this->drawHtml('checkbox', 'Send Push Notification', 'push_notification', $request->old('push_notification'), null, '', 'col-md-12 '),
                    ]
                ], [
                    'wrapper-class' => 'col-md-6',
                    'class' => 'box-primary',
                    'box-header' => 'Push Notification',
                    'form_fields' => [
                        $this->drawHtml('small_text', 'Name', 'name_notification', $request->old('name'), null, '', 'col-md-12 '),
                        $this->drawHtml('text', 'Details', 'details_notification', $request->old('details'), null, '', 'col-md-12'),
                        $this->drawHtml('checkbox', 'Use news\' image?', 'use_news_image', $request->old('push_notification'), null, '', 'col-md-12 '),
                        $this->drawHtml('image', 'Image', 'image_notification', null, null, '', 'col-md-12')
                    ]
                ]
            ]
        ]);
    }



    public function uploadAttachement(Request $request)
    {
        $this->validate($request, [
            'myfile' => 'required|max:700',
        ]);

        $image = $request->file('myfile');
        return $this->moveFile($image, 'images/news/attachements');
    }



    public function deleteAttachement(Request $request)
    {
        return response()->json($request->name);
        $name = request('name');

        $this->removeFile('/news/attachements/' . $name);

        return $name;
    }

    public function store(Request $request)
    {
        ini_set('memory_limit', '-1');
        ini_set('upload_max_filesize', '200M');
        ini_set('post_max_size', '200M');
        ini_set('client_max_body_size', '200M');
        ini_set('max_execution_time', 3600);

        $this->validate($request, [
            'title' => 'required_without_all:images,attachements,video|required_with:details',
            'source' => 'required',
            'details' => 'required_with:title',
            'date' => 'date',
            'groups' => 'required_without:all_groups',
            "attachment" => "mimes:pdf",
            //'video' => '',
            'thumbnail' => 'required_with:video|mimes:jpeg,png,jpg,gif,svg|max:4000',
            'source_image' => 'nullable|mimes:jpeg,png,jpg,gif,svg|max:4000',

            #notification
            'image_notification' => 'mimes:jpeg,png,jpg,gif,svg|max:700',
            'details_notification' => 'required_with:name_notification',
            'name_notification' => 'required_with:details_notification'
        ]);


        $news = new News();

        $news->setTranslations('title', [
            'en' => request('title'),
            'ar' => request('title_ar'),
        ]);

        $news->setTranslations('source', [
            'en' => request('source'),
            'ar' => request('source_ar'),
        ]);

        $news->setTranslations('details', [
            'en' => request('details'),
            'ar' => request('details_ar'),
        ]);


        $news->date = Carbon::parse(request('date'))->toDateTimeString();

        $news->strict_lang = request('strict_lang');

        if (request('video')) {
            $news->file = $this->moveFile(request('video'), 'news/videos');
            $news->thumbnail = $this->moveFile(request('thumbnail'), 'news/videos/thumbnails');
            /* $news->type = 'video'; */
        }
        if (request('source_image')) {
            $news->source_image = $this->moveFile(request('source_image'), "images/news/");
        }
        $news->save();

        if (request("attachment")) {
            $attachementName = $this->moveFile(request("attachment"), 'news/attachments');
            NewsAttachement::create(["name" => $attachementName, "news_id" => $news->id]);
        }

        //Save images
        if (request('images')) {
            $new_images = request('images') ? json_decode(request('images')) : [];
            foreach ($new_images as $image) {
                NewsImage::create(["name" => $image, "news_id" => $news->id]);
            }
        }

        if (request('all_groups')) {
            $groups = Group::all()->pluck('group_id')->toArray();
            $request->merge(['groups' => $groups]);
        }

        $news->groups()->sync(request('groups'));

        //push notification
        if (request('push_notification')) {
            if (request('name_notification')) {
                $image = null;

                if (request("use_news_image")) {
                    $news_image = $news->images->first();
                    if ($news_image) {
                        $image = Storage::disk('s3')->url(env('AWS_BUCKET_PROJECT_NAME') . '/' . 'storage/' . 'images/news/' . $news_image->name) ;
                    }
                } else {
                    if (request('image_notification')) {
                        $image = "images/notification_images/" . $this->moveFile(request('image_notification'), 'images/notification_images');
                        $image = Storage::disk('s3')->url(env('AWS_BUCKET_PROJECT_NAME') . '/' . 'storage/' . $image);
                    }
                }

                $request->request->add([
                    'title' => request('name_notification'),
                    'text' => request('details_notification'),
                    'image' => ($image) ? $image : null,
                    'image_path' => ($image) ? $image : null,
                ]);
            } else {
                $request->request->add([
                    'title' => request('title'),
                    'title_ar' => request('title_ar'),
                    'text' => request('details'),
                    'text_ar' => request('details_ar'),
                    'image' => ($image = $news->images->first()) ? Storage::disk('s3')->url(env('AWS_BUCKET_PROJECT_NAME') . '/' . 'storage/' . $image->name) : null,
                    'image_path' => ($image = $news->images->first()) ? Storage::disk('s3')->url(env('AWS_BUCKET_PROJECT_NAME') . '/' . 'storage/' . $image->name) : null
                ]);
            }

            //add the news to the request for the event
            $request->request->add(['news' => $news->id]);
            // dd($request);
            event(new NewItem($request));
        }

        return redirect()->route('admin.news.index')->with('message', 'News has been created successfully');
    }


    public function imageUpload(Request $request)
    {
        $this->validate($request, [
            'myfile' => 'required|max:700',
        ]);

        $image = $request->file('myfile');
        return $this->moveFile($image, 'images/news/');
    }


    public function deleteUpload(Request $request)
    {
        $name = request('name');
        $this->removeFile('images/news/' . $name);
        return $name;
    }

    //used by image_ajax_deleter.js
    public function deleteNewsImage($id)
    {
        $image = NewsImage::find($id);

        if (!$image) {
            return response()->json(['message' => "the image not found"], 404);
        }

        $this->removeFile('images/news/' . $image->name);

        $image->delete();

        return response()->json(['message' => "the image has been deleted successfully"]);
    }


    public function deleteUploadEdit(Request $request)
    {
        $name = request('name');
        if (is_array($name))
            return $name[0];
        return $name;
    }


    //Used to delete by pdf_ajax_deleter.js
    public function deletePdf($id)
    {
        $pdf = NewsAttachement::find($id);

        if (!$pdf) {
            return response()->json(['message' => "the pdf not found"], 404);
        }

        $this->removeFile('news/attachments/' . $pdf->name);

        $pdf->delete();

        return response()->json(['message' => "the pdf has been deleted successfully"]);
    }




    public function edit($id, Request $request)
    {
        $groups = Group::all()->pluck('name', 'group_id')->toArray();
        $news = News::find($id);

        $default_groups = !$news->groups()->get()->isempty() ? $news->groups()->get()->pluck('group_id')->toArray() : '';

        return view('components.form')->with([
            'layout'         => 'layouts.cms',
            'pageTitle'        => 'Edit News',
            'method'        => 'update',
            'form_action'    => route('admin.news.update', $id),

            'boxes' => [
                [
                    'wrapper-class' => 'col-md-12',
                    'class' => 'box-default',
                    'box-header' => 'Info',
                    'form_fields' => [
                        $this->drawHtml('select-box', 'Force Language', 'strict_lang', $news->strict_lang, $this->strictLang(), '', 'col-md-12 required'),


                        $this->drawHtml('small_text', 'Title', 'title', $news->getTranslation('title', 'en'), null, '', 'col-md-6 required'),
                        $this->drawHtml('small_text', 'Title(Arabic)', 'title_ar', $news->getTranslation('title', 'ar'), null, '', 'col-md-6 right-to-left required'),


                        $this->drawHtml('small_text', 'Source', 'source', $news->getTranslation('source', 'en'), null, '', 'col-md-6 '),
                        $this->drawHtml('small_text', 'Source(Arabic)', 'source_ar', $news->getTranslation('source', 'ar'), null, '', 'col-md-6 right-to-left'),

                        $this->drawHtml('text', 'Details', 'details', $news->getTranslation('details', 'en'), null, '', 'col-md-6 required'),
                        $this->drawHtml('text', 'Details(Arabic)', 'details_ar', $news->getTranslation('details', 'ar'), null, '', 'col-md-6 right-to-left required'),

                        $this->drawHtml('date-time-picker', 'Date', 'date', $news->date, null, '', 'col-md-12 required'),
                    ],
                ],
                [
                    'wrapper-class' => 'col-md-12',
                    'class' => 'box-primary',
                    'box-header' => 'Video ',
                    'form_fields' => [
                        (($news->type == 'video' || (!$news->type && $news->file)) ? $this->drawVideo("/news/videos/" . $news->file) : ""),
                        $this->drawHtml('file', 'Upload Video (will override the existing one)', 'video', null, 'video/mp4', '', 'col-md-12 form-group'),

                        ($news->thumbnail ? $this->drawImage('news/videos/thumbnails/' . $news->thumbnail, "100", "col-md-2") : ""),
                        $this->drawHtml('file', 'Attach Thumbnail (will override the existing one)', 'thumbnail', null, 'image/*', '', 'col-md-10'),
                    ]
                ], [
                    'wrapper-class' => 'col-md-12 ',
                    'class' => 'box-primary',
                    'box-header' => 'Images',
                    'form_fields' => [
                        $this->drawImage('images/news/' . $news->source_image, '100', "col-md-2"),
                        $this->drawHtml('file', 'Upload Source Image (will override the existing one)', 'source_image', null, 'image/*', '', 'col-md-10'),

                        $this->drawDeletableImages($news->images, "img-fluid rounded", 200, ['delete' => route('admin.newsimage.delete')]),
                        $this->drawHtml('multiple-file-upload', 'Upload Images (will be added to the existing ones)', 'images', null, ['add' => route('admin.newsimage.upload'), 'delete' => route('admin.newsimage.delete'), 'default' => null], '', 'col-md-12'),
                    ]
                ],
                [
                    'wrapper-class' => 'col-md-12 ',
                    'class' => 'box-primary',
                    'box-header' => 'PDF',
                    'form_fields' => [
                        $this->drawDeletablePdfs($news->attachments, "news/attachments/"),
                        $this->drawHtml('pdf', "Upload PDF (will override the existing one)", "attachment", null, 'application/pdf', null, "form-group col-md-12"),
                    ]
                ], [
                    'wrapper-class' => 'col-md-12 ',
                    'class' => 'box-primary',
                    'box-header' => 'Permissions',
                    'form_fields' => [
                        $this->drawHtml('multiple-select-box', 'Groups', 'groups[]', $default_groups, $groups, '', 'col-md-12 '),
                    ]
                ],


            ]
        ]);
    }


    public function update($id, Request $request)
    {
        $this->validate($request, [
            'title' => 'required_with:details',
            'source' => 'required',
            'details' => 'required_with:title',
            'date' => 'date',
            'groups' => 'required_without:all_groups',
            'thumbnail' => 'nullable|mimes:jpeg,png,jpg,gif,svg|max:4000',
            'source_image' => 'nullable|max:4000',
        ]);

        $news = News::find($id);
        $news->setTranslations('title', [
            'en' => request('title'),
            'ar' => request('title_ar'),
        ]);

        $news->setTranslations('source', [
            'en' => request('source'),
            'ar' => request('source_ar'),
        ]);

        $news->setTranslations('details', [
            'en' => request('details'),
            'ar' => request('details_ar'),
        ]);

        $news->date = Carbon::parse(request('date'))->toDateTimeString();

        $news->strict_lang = request('strict_lang');

        if (request('video')) {
            $this->removeFile($news->file);
            /* $news->type = 'video'; */ //DO NOT SET THE TYPE WEN IT IS VIDEO
            $news->file = $this->moveFile(request('video'), 'news/videos/');
        }

        if (request('thumbnail')) {
            $news->thumbnail = $this->moveFile(request('thumbnail'), 'news/videos/thumbnails');
            /* $news->type = 'video'; */  //DO NOT SET THE TYPE WEN IT IS VIDEO
        }

        if (request('source_image')) {
            //Remove the existing one
            if ($news->source_image) $this->removeFile($news->source_image);
            $news->source_image = $this->moveFile(request('source_image'), "images/news/");
        }


        if (request('all_groups')) {
            $groups = Group::all()->pluck('group_id')->toArray();
            $request->merge(['groups' => $groups]);
        }


        $news->groups()->sync(request('groups'));

        $news->save();

        if (request("attachment")) {
            //Remove the existing one
            if ($news->attachments->count() > 0) {
                $pdf = $news->attachments->first();

                $this->removeFile("news/attachments/$pdf->name");
                $pdf->delete();
            }

            $attachementName = $this->moveFile(request("attachment"), 'news/attachments');
            NewsAttachement::create(["name" => $attachementName, "news_id" => $news->id]);
        }

        //Save Source images
        $new_images = request('images') ? json_decode(request('images')) : [];
        foreach ($new_images as $image) {
            NewsImage::create(["name" => $image, "news_id" => $news->id]);
        }

        return redirect()->route('admin.news.index')->with('message', 'News has been created successfully');
    }

    private function strictLang()
    {
        return [
            null => 'None',
            'ar' => 'Ar',
            'en' => 'En',
        ];
    }

    public function destroy($id)
    {
        $news = News::find($id);
        $this->removeFile($news->image);

        $images = $news->images;

        foreach ($images as $image) {
            $this->removeFile("images/news/" . $image->name);
            $image->delete();
        }

        $attachments = $news->attachments;
        foreach ($attachments as $attachment) {
            $this->removeFile("news/attachments/" . $attachment->name);
            $attachment->delete();
        }

        if ($news->file) {
            $this->removeFile($news->file);
            $this->removeFile($news->thumbnail);
        }

        if ($news->source_image) {
            $this->removeFile($news->source_image);
        }

        $news->delete();

        return back()->with('message', 'News Deleted Successfully');
    }
}
