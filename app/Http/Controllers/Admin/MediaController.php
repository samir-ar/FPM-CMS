<?php

namespace App\Http\Controllers\Admin;

use App\V2\Album;
use Illuminate\Http\Request;
use App\V2\Media;
use App\Http\Controllers\Controller;

use App\Http\Traits\FormTrait;
use App\Http\Traits\FileTrait;
use DataTables;


use Illuminate\Support\Facades\File;
use Image;
use Storage;

class MediaController extends Controller
{
    use FormTrait;
    use FileTrait;

    public function index(Album $album, Request $request){

        if($request->ajax()) {
            $data = $album->medias;


            return DataTables::of($data)


                ->addColumn('preview', function($row){
                        $path = ($row->type == 'images')?"media/":"media/thumbnail/";
                        return "<img class='img-thumbnail' src='".asset('media/thumbnail/' . $row->thumbnail)."' width='200px'>";
                })

                ->addColumn('name', function($row){
                    return $row->getTranslation('name', 'ar');
                })

                ->addColumn('action', function($row) use ($album){
                    return "<a class='edit-link' href='" . route('admin.media.edit',['type'=>$row->type,'album'=>$album,'media'=>$row] ) . "'>".
                        '<i class="fa fa-edit" aria-hidden="true"></i></a>'.
                        "<a data-toggle='modal' class='delete-link' href='#deleteModal' id='" .route('admin.media.destroy', $row->id) . "'>".
                        "<i class='fa fa-trash' style='color: red;' aria-hidden='true'></i>";
                })
                ->rawColumns(['id', 'name','description','date','action','media','preview'])
                ->make(true);
        }


        return view('components.table_ajax')->with([
            'layout'    => 'layouts.cms',
            'pageTitle'	=> '',
            'table_title' => '',
            'slug'		=> 'Archives',
            'custom_btn' => "<a href='" . route('admin.media.create', ['type'=>$album->type,'id'=>$album->id]) ."' class='btn btn-primary'>Add Media</a>",
            'headers'	=> ['id','Preview','Name', 'Action'],
            'action' => route('admin.albums.index'),
            'columns' => json_encode([
                ['data' => 'id', 'name' => 'id'],
                ['data' =>  'preview', 'name'=> 'preview'],
                ['data' =>  'name', 'name'=> 'name'],
                ['data' => 'action', 'name' => 'action', 'searchable' => false, 'sortable' => false],
            ]),
        ]);
    }

    public function create($type, $id, Request $request){

        $fileInput = "";
        if($type === "images") $fileInput = $this->drawHtml('image', 'Upload Image', 'images', '','', '', 'col-md-12  required');
        if($type === "videos" || $type === "pdfs") $fileInput = $this->drawHtml('image', 'Upload Thumbnail', 'thumbnail', null,null , '', 'col-md-6 required ');
        if($type === "videos") $fileInput = $this->drawHtml('file', 'Upload Video', 'video', '',"video/*" , '', 'col-md-6 required ');
        if($type === "videos") $fileInput .= $this->drawHtml('small_text', 'Or past Youtube URL', 'youtube', '',"" , '', 'col-md-6 required ');
        if($type === "pdfs") $fileInput = $this->drawHtml('file', 'Upload pdf', 'pdf', '',"application/pdf" , '', 'col-md-12 required ');

        return view('components.form')->with([
            'layout'         => 'layouts.cms',
            'pageTitle'		=> 'Add New Item',
            'method'		=> 'post',
            'form_action'	=> route('admin.media.store',$id),

            'boxes' => [
                [
                    'wrapper-class' => 'col-md-12',
                    'class' => 'box-default',
                    'box-header' => 'Uploads',
                    'form_fields' => [

                        $this->drawHtml('small_text', 'Name', 'name', '', null, '', 'col-md-12 required'),
                        ($type=="images")?"":$this->drawHtml('file', 'Upload Thumbnail', 'thumbnail', '',"image/*" , '', 'col-md-12 required'),
                        $fileInput,
                        $this->drawHtml('hidden', '', 'type', $type, null, '', 'col-md-6 required'),

                        /*
                        $this->drawHtml('text', 'Description (In English)', 'description', '', null, '', 'col-md-6 required'),
                        $this->drawHtml('text', 'وصف (بالعربية)', 'description_ar', '', null, '', 'col-md-6 required right-to-left'), */

                        //$this->drawHtml('select-box', 'Type', 'type', '', array("videos"=>"videos","pdfs"=>"pdfs","images"=>"images","not defined"=>"not defined"), '', 'col-md-12 required'),

                        //$this->drawHtml('file', 'File', 'file', '', null, '', 'col-md-12 required'),
                    ],

                    ]
                    ]
                ]);
                //form
                //list of items (use drawDeletableItem)
            }

    public function store($id,Request $request){
        $this->validate($request, [
         /* 'name' => 'required',
            'name_ar' => 'required',*/
            'thumbnail' => 'required_without:images',
            'pdf' => 'required_without_all:video,images,youtube',
            'video' => 'required_without_all:pdf,images,youtube| mimes:mp4,mov,ogg,qt,3gp,3gpp | max:20000',
            'images' => 'required_without_all:pdf,video,youtube',
            'youtube' => 'required_without_all:video,images,pdf',
            'type' => 'required|in:videos,pdfs,images'
         /* 'description' => 'required',
            'description_ar' => 'required',*/
        ]);

        if(request('images')){
            $file = $this->copyFile(request('images'),"media");
            $thumbnail = $this->copyFile(request('images'),"media/thumbnail");
            Media::create(["file_name"=>$thumbnail,'name'=>request('name'),"album_id"=>$id, "thumbnail"=>$thumbnail, "type"=>request('type')]);

            /*$images = request('images') ? json_decode(request('images')) : [];

            foreach ($images as $image){
                $image = explode(",", $image);
                Media::create(["file_name"=>$image[0],"album_id"=>$id, "thumbnail"=>$image[1], "type"=>request('type')]);
            }*/

        }else{
            $media = new Media();
            $media->name = request('name');

                /*
                    /*create medium thumbnail
                    $mediumthumbnailpath = public_path('media/thumbnail/'.$mediaName);
                    $this->createThumbnail($mediumthumbnailpath, 300, 300);
                    */

                    $media->type = request('type');

                    $media->album_id=$id;
                    if(request('video') && !request('youtube')){
                        $media->file_name =  $this->moveFile(request('video'),"media");
                    }

                    //The link is more important than the video
                    if(request('youtube')){
                        $media->youtube =  request('youtube');
                    }

                    if(request('pdf')){
                        $media->file_name =  $this->moveFile(request('pdf'),"media");
                    }

                    $media->thumbnail = $this->moveFile(request('thumbnail'),"media/thumbnail");

                    $media->save();
        }
        return redirect("/admin/media-create/".request('type')."/$id")->with('message', 'Item has been added. Add another one');
    }

    public function edit($type,Album $album, Media $media){
        $fileInput = "";
        if($type === "images") $fileInput = $this->drawHtml('image', 'Upload Image', 'images', '','', '', 'col-md-12  required');
        if($type === "videos" || $type === "pdfs") $fileInput = $this->drawHtml('image', 'Upload Thumbnail', 'thumbnail', "media/thumbnail/".$media->thumbnail,null , '', 'col-md-6 required ');
        if($type === "videos") $fileInput .= $this->drawHtml('file', 'Upload Video', 'video', '',"video/*" , '', 'col-md-6 required ');
        if($type === "videos") $fileInput .= $this->drawVideo("media/".$media->file_name);
        if($type === "videos") $fileInput .= $this->drawHtml('small_text', 'Or past Youtube URL', 'youtube', $media->youtube,"" , '', 'col-md-6 required ');
        if($type === "pdfs") $fileInput .= $this->drawHtml('file', 'Upload pdf', 'pdf', "","application/pdf" , '', 'col-md-12 required ');
        if($type === "pdfs") $fileInput .= "<div class='col-md-12 px-4'><a target='_blank' href='".url("media/".$media->file_name)."'>".$media->file_name."</a></div>";


        return view('components.form')->with([
            'layout'         => 'layouts.cms',
            'pageTitle'		=> 'Edit Media Details',
            'method'		=> 'post',
            'form_action'	=> route('admin.media.update', ['type'=>$media->type,'album'=>$album,'media'=>$media]),

            'boxes' => [
                [
                    'wrapper-class' => 'col-md-12',
                    'class' => 'box-default',
                    'box-header' => 'Info',
                    'form_fields' => [
                        $this->drawHtml('hidden', '', 'type', $type,null, '', 'col-md-12 required'),
                        $this->drawHtml('small_text', 'عنوان (بالعربية) ', 'name_ar', $media->getTranslation('name','ar'), null, '', 'col-md-6 required right-to-left'),
                       /* $this->drawHtml('image', 'Image', 'name', url("media/".$media->thumbnail),null, '', 'col-md-12 required'),*/
                        $fileInput,
                     ],
                ]
            ]
        ]);
    }

    public function update($type,Album $album,Media $media,Request $request){
        $this->validate($request, [
            'name_ar' => 'required',
         /*   'thumbnail' => 'required_without:images',
            'pdf' => 'required_without_all:video,images,youtube',
            'video' => 'required_without_all:pdf,images,youtube| mimes:mp4,mov,ogg,qt,3gp,3gpp | max:20000',
            'images' => '',
            'youtube' => 'required_without_all:video,images,pdf',*/
            'type' => 'required|in:videos,pdfs,images'
        ]);

        //Only one lang
        $media->setTranslations('name', [
            'en' => request('name_ar'),
            'ar' => request('name_ar'),
        ]);

       /* $media->setTranslations('description', [
            'en' => request('description'),
            'ar' => request('description_ar'),
        ]);*/

        if($request->thumbnail){
            if($media->thumbnail && File::exists($media->thumbnail)){
                File::delete($media->thumbnail);
            }
            $media->thumbnail = $this->moveFile(request('thumbnail'),"media/thumbnail");
        }
        if($request->images){
            //Update Image
            if($media->file_name && File::exists($media->file_name)){
                File::delete($media->file_name);
            }
            $media->file_name = $this->copyFile(request('images'),"media");//$this->moveFile(request('images'),"media");

            //Update image stored inside the thumbnail
            if($media->thumbnail && File::exists($media->thumbnail)){
                File::delete($media->thumbnail);
            }

            $media->thumbnail = $this->copyFile(request('images'),"media/thumbnail");//$this->moveFile(request('images'),"media/thumbnail");
        }

        if($request->video){
            if($media->file_name && File::exists($media->file_name)){
                File::delete($media->file_name);
            }
            $media->file_name = $this->moveFile(request('video'),"media");
        }

        if($request->pdf){
            if($media->file_name && File::exists($media->file_name)){
                File::delete($media->file_name);
            }
            $media->file_name = $this->moveFile(request('pdf'),"media");
        }

        if($request->youtube){
            $media->youtube = $request->youtube;
        }

        $media->save();
        return redirect()->route('admin.media.index',['album'=>$album])->with('message', 'Media\'s Details has Been Updated Successfully');
    }


    public function delete($id){
        $media = Media::find($id);

        if(!$media){
            return response()->json(['message' =>"media not found"],404);
        }

        $this->removeFile('media/'.$media->file_name);
        $this->removeFile('media/thumbnail/'.$media->thumbnail);
        $media->delete();

        return response()->json(['message' =>"The item has been removed successfully"],200);
    }


    public function uploadImage(Request $request){
        $this->validate($request, [
            'myfile' => 'required|max:700',
        ]);

        //Create Thumbnail
        $file = $request->file('myfile');

        try{
            $thumbnail = $this->copyFile(request('myfile'),"media/thumbnail");//$this->moveFile($file, 'media');
        }catch(Exception $e){ return $e->getMessage();}
        $image = $this->moveFile($file, 'media/');

        //TODO create real thumbnail
        return "$image,$thumbnail";
    }



    public function deleteImage(Request $request)
    {

        if(request('id')){
            $media = Media::find(request('id'));
            $media->delete();
        }
        $name = explode(",",request('name'));
        $this->removeFile('media/'.$name[0]);
        $this->removeFile('media/thumbnail'.$name[1]);
        return $name;
    }



    public function deleteUpload(Request $request)
    {
        if(request('id')){
            $media = Media::find(request('id'));
            $file =  $media->file_name;
            $thumbnail =  $media->thumbnail;
            $this->removeFile('media/'.$file);
            $this->removeFile('media/thumbnail/'.$thumbnail);
            $media->delete();

            return response()->json(["message" =>"sucess"],200);
        }
        return response()->json(["message"=>"file not found"],404);

    }
    public function createThumbnail($path, $width, $height)
    {
        $img = Image::make($path)->resize($width, $height, function ($constraint) {
            $constraint->aspectRatio();
        });
        $img->save($path);
    }

    public function destroy($id){
        $media = Media::find($id);
        $media->delete();
        return back()->with('message', 'Media has Been Deleted Successfully');
    }
}
