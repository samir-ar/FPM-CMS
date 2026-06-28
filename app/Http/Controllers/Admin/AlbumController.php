<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\V2\Album;
use App\V2\Media;

use App\Http\Traits\FormTrait;
use App\Http\Traits\FileTrait;
use App\Http\Controllers\Controller;
use DataTables;


class AlbumController extends Controller
{

    use FormTrait;
    use FileTrait;
    public function index(Request $request){

        if($request->ajax()) {
            $query = new Album();

            if(request()->query('type')){
                $query= $query->where('type', request()->query('type'));
            }

            $data = $query->get();


            return DataTables::of($data)

            ->addColumn('name', function($row){
                return $row->getTranslation('name', 'ar');
                })
                
                ->addColumn('date', function($row){
                     return $row->created_at;
                 })

                 ->addColumn('description', function($row){
                    return $row->getTranslation('description', 'ar');
                 })

                ->addColumn('media', function($row){
                    return "<a href='".route('admin.media.index',$row)."'> media </a>";
                })

                 ->addColumn('action', function($row){
                    return "<a class='edit-link' href='" . route('admin.albums.edit', $row->id) . "'>".
                        '<i class="fa fa-edit" aria-hidden="true"></i></a>'.
                        "<a data-toggle='modal' class='delete-link' href='#deleteModal' id='" .route('admin.albums.destroy', $row->id) . "'>".
                        "<i class='fa fa-trash' style='color: red;' aria-hidden='true'></i>";
                })
                ->rawColumns(['id', 'name','description','date','action','media'])
                ->make(true);
        }


        return view('components.table_ajax')->with([
            'layout'    => 'layouts.cms',
            'pageTitle'	=> 'Archives'.((request()->query('type'))?" - ".ucfirst(request()->query('type')):""),
            'table_title' => '',
            'slug'		=> 'Archives',
            'custom_btn' => "<a href='" . route('admin.albums.create') ."' class='btn btn-primary'>Add Album</a>",
            'headers'	=> ['id','Name' ,'Description' ,'Media', 'Date', 'Action'],
            'action' => route('admin.albums.index'),
            'columns' => json_encode([
                ['data' => 'id', 'name' => 'id'],
                ['data' =>  'name', 'name'=> 'name'],
                ['data' =>  'description', 'name'=> 'description'],
                ['data' =>  'media', 'name'=> 'media'],
                ['data' =>  'date', 'name'=> 'date'],
                ['data' => 'action', 'name' => 'action', 'searchable' => false, 'sortable' => false],
            ]),
        ]);
    }


    public function destroy($id){
        $album = Album::find($id);

        if(!$album){
            return back()->with('error', 'Album Not found');
        }

        if($album->thumbnail){
            $this->removeFile('/media/thumbnail/'.$album->thumbnail);
        }

        $album->medias->each(function($m){
            $this->removeFile('/media/'.$m->file_name);
            $this->removeFile('/media/thumbnail/'.$m->thumbnail);
        });

        $album->delete();

        return back()->with('message', 'Album Deleted Successfully');
    }

    public function edit($id, Request $request){

        $album = Album::find($id);
        return view('components.form')->with([
            'layout'         => 'layouts.cms',
            'pageTitle'		=> 'Edit Media Details',
            'method'		=> 'post',
            'form_action'	=> route('admin.albums.update', $id),

            'boxes' => [
                [
                    'wrapper-class' => 'col-md-12',
                    'class' => 'box-default',
                    'box-header' => 'Info',
                    'form_fields' => [
                        $this->drawHtml('hidden', '', 'type', $album->type,null, '', 'col-md-6 required'),
                        $this->drawHtml('small_text', 'Title (In English)', 'name', $album->getTranslation('name', 'en'),null, '', 'col-md-6 required'),
                        $this->drawHtml('small_text', 'عنوان (بالعربية) ', 'name_ar', $album->getTranslation('name','ar'), null, '', 'col-md-6 required right-to-left'),
                        $this->drawHtml('text', 'Description (In English)', 'description', $album->getTranslation('description', 'en'), null, '', 'col-md-6 required'),
                        $this->drawHtml('text', 'وصف (بالعربية)', 'descriptionar', $album->getTranslation('description', 'ar'),  null, '', 'col-md-6 required right-to-left')
                    ],
                ],
                [
                    'wrapper-class' => 'col-md-12',
                    'class' =>   'box-primary',
                    'box-header' => 'Media',
                    'form_fields' => [
                            "<a target='__blank' href='".url('admin/media-create/'.$album->type.'/'.$album->id.'/') ."' style='margin-bottom: 25px;margin-left: 15px; ' class='btn btn-success' >Add New Media</a>",
                            ($album->type === 'images')?$this->drawDeletableMediaImages($album->medias,'col-md-12',"/admin/media-delete"):(($album->type == 'pdfs')?$this->drawAlbumDeletablePdfs($album->medias, "admin/media-delete" ):$this->drawDeletableVideos($album->medias, "admin/media-delete" , null, 'col-md-12 ')),
                        ]
                ]
            ]
        ]);
    }


    public function update($id , Request $request){

        $this->validate($request, [
            'name' => 'required',
            'name_ar' => 'required',
            'description' => 'required',
            'descriptionar' => 'required',
            'type' => 'required|in:videos,pdfs,images',

        ]);

        $album = Album::find($id);

            if($album->medias->count() === 0 && ( !request('video') && !request('pdf') && !request('images'))){
                $album->delete();
                return redirect("/admin/albums?type=".request('type'))->withErrors(['The item cannot be saved without any media for that it has been deleted']);
            }


        $album->setTranslations('name', [
            'en' => request('name'),
            'ar' => request('name_ar'),
        ]);

         $album->setTranslations('description', [
            'en' => request('description'),
            'ar' => request('descriptionar'),
        ]);

        $album->type = request('type');
        $album->save();

        if(request("thumbnail")){
            $this->removeFile("/media/thumbnail/$album->thumbnail");
            $album->thumbnail = $this->moveFile(request('thumbnail'),"media/thumbnail");
            $album->save();
        }

        if(request("type") == "videos" && request("video")){
            $media = Media::where('album_id', $album->id)->first();

            if($media){
                $this->removeFile("media/".$media->file_name);
                $media->file_name =  $this->moveFile(request("video"),"media/");
                $media->save();
            }else{
                $fileName= $this->moveFile(request("video"),"media/");
                Media::create(["file_name"=>$fileName, "album_id"=>$album->id, "type"=>"videos"]);
            }

        }


        if(request("type") == "pdfs" && request("pdf")){
            $media = Media::where('album_id', $album->id)->first();

            if($media){
                $this->removeFile("media/".$media->file_name);
                $media->file_name =  $this->moveFile(request("pdf"),"media/");
                $media->save();
            }else{
                $fileName= $this->moveFile(request("pdf"),"media/");
                Media::create(["file_name"=>$fileName, "album_id"=>$album->id, "type"=>"pdfs"]);
            }

        }

        if(request("images")){
            $images = request('images') ? json_decode(request('images')) : [];
            foreach ($images as $image){
                 Media::create(["file_name"=>$image,"album_id"=>$album->id, "type"=>$album->type]);
            }
        }
        return back()->with('message', 'Album\'s Details has Been Updated Successfully');
    }

    public function store(Request $request){
        $this->validate($request, [
            'name' => 'required',
            'name_ar' => 'required',
            'description' => 'required',
            'type' => 'required|in:videos,pdfs,images',
            'description_ar' => 'required',
            "thumbnail" => 'required',
            /*"pdf" => 'required_if:type,pdfs',
            "video" => 'required_if:type,videos',
            "images" => 'required_if:type,images' */
        ]);

        $album = new Album();

        $album->setTranslations('name', [
            'en' => request('name'),
            'ar' => request('name_ar'),
        ]);

        $album->thumbnail = $this->moveFile(request('thumbnail'),"media/thumbnail");

        $album->setTranslations('description', [
            'en' => request('description'),
            'ar' => request('description_ar')
        ]);

        $album->type = request('type');

        $album->save();

/*         if(request('video')||request('pdf')){
            $media = new Media();
            $media->type = request('type');
            $media->album_id = $album->id;
            $media->file_name = $this->moveFile((request('video'))?request('video'):request('pdf'),"media/");
            $media->save();
        }

        if(request("images")){

            $images = request('images') ? json_decode(request('images')) : [];
            foreach ($images as $image){
                 Media::create(["file_name"=>$image,"album_id"=>$album->id, "type"=>$album->type]);
            }
        } */

        //return redirect()->route('admin.albums.'.request('type'))->with('message', 'Media has been created successfully');
        return redirect("admin/media-create/".request('type')."/$album->id")->with('message', 'Album has been created successfully please add some medias');
    }


    public function imageUpload(Request $request)
    {
        $this->validate($request, [
            'myfile' => 'required|max:700',
        ]);

        $image = $request->file('myfile');
        return $this->moveFile($image, 'media/');
    }


    public function create(Request $request){

        return view('components.form')->with([
            'layout'         => 'layouts.cms',
            'pageTitle'		=> 'Create an album',
            'method'		=> 'post',
            'form_action'	=> route('admin.albums.store'),

            'boxes' => [
                [
                    'wrapper-class' => 'col-md-12',
                    'class' => 'box-default',
                    'box-header' => 'Info',
                    'form_fields' => [
                        $this->drawHtml('select-box', 'Type', 'type', '', array("videos"=>"videos","pdfs"=>"pdfs","images"=>"images"), '', 'col-md-12 required changeMediaType'),

                        $this->drawHtml('small_text', 'Title (In English)', 'name', '',null, '', 'col-md-6 required'),
                        $this->drawHtml('small_text', 'عنوان (بالعربية) ', 'name_ar', '', null, '', 'col-md-6 required right-to-left'),

                        $this->drawHtml('text', 'Description (In English)', 'description', '', null, '', 'col-md-6 required'),
                        $this->drawHtml('text', 'وصف (بالعربية)', 'description_ar', '', null, '', 'col-md-6 required right-to-left'),
                        $this->drawHtml('file', 'Upload Thumbnail', 'thumbnail', '',"image/*" , '', 'col-md-12 required'),

               /*          $this->drawHtml('multiple-file-upload', 'Upload Images', 'images', '', ['add' => route('admin.media.upload') , 'delete' => route('admin.media.delete'), 'default' => null] , '', 'col-md-12  required multiImageUpload'),
                        $this->drawHtml('file', 'Upload Video', 'video', '',"video/*" , '', 'col-md-12 required videoUploader'),
                        $this->drawHtml('file', 'Upload pdf', 'pdf', '',"application/pdf" , '', 'col-md-12 required pdfUploader') */
                    ],
                ],
            ]
        ]);
    }
}
