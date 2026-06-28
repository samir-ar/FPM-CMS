<?php

namespace App\Http\Controllers\Admin;

use App\Content;
use Illuminate\Http\Request;
use App\Http\Traits\FormTrait;
use App\Http\Traits\FileTrait;
use App\Http\Controllers\Controller;


class ContentController extends Controller
{
    use FormTrait;
    use FileTrait;

    public function representativesForm(Request $request)
    {
        $r = Content::where('category', 'representative-content')->first();

        return view('components.form')->with([
            'layout'         => 'layouts.cms',
            'pageTitle'		=> 'Representative Page Content',
            'method'		=> 'post',
            'form_action'	=> route('admin.representatives.update_form'),

            'boxes' => [
                [
                    'wrapper-class' => 'col-md-6',
                    'class' => 'box-default',
                    'box-header' => 'Info',
                    'form_fields' => [
                        $this->drawHtml('small_text', 'title(English)', 'title', $r ? $r->title : $request->old('title'), null, '', 'col-md-12'),
                        $this->drawHtml('small_text', 'title(Arabic)', 'title_ar', $r ? $r->title_ar : $request->old('title_ar'), null, '', 'col-md-12 right-to-left'),

                        $this->drawHtml('text', 'Text(English)', 'text', $r ? $r->text : $request->old('text'), null, '', 'col-md-12'),
                        $this->drawHtml('text', 'Text(Arabic)', 'text_ar', $r ? $r->text_ar : $request->old('text_ar'), null, '', 'col-md-12  right-to-left'),

                    ],
                ],
            ]
        ]);
    }

    public function representativesUpdate(Request $request)
    {
        $this->validate($request, [
            'title' => 'required',

            'text' => 'required',
        ]);

        $r = Content::where('category', 'representative-content')->first();

        if(!$r){
            $r = new Content;
            $r->category = 'representative-content';
        }

        $r->title = request('title');
        $r->title_ar = request('title_ar');
        $r->text = request('text');
        $r->text_ar = request('text_ar');

        $r->save();

        return back()->with('message', 'Content Updated Successfully');
    }

    public function aboutUsForm(Request $request)
    {

        $content = Content::where('category', 'about-us-content')->first();
        $media = Content::where('category', 'about-us-social-media')->first();


        if($media){
            $media = json_decode($media->text);
        }

        return view('components.form')->with([
            'layout'         => 'layouts.cms',
            'pageTitle'		=> 'Representative Page Content',
            'method'		=> 'post',
            'form_action'	=> route('admin.aboutUs.update'),

            'boxes' => [
                [
                    'wrapper-class' => 'col-md-6',
                    'class' => 'box-default',
                    'box-header' => 'Content',
                    'form_fields' => [
                        $this->drawHtml('text', 'Text(English)', 'text', $content ? $content->text : $request->old('text'), null, '', 'col-md-12'),
                        $this->drawHtml('text', 'Text(Arabic)', 'text_ar', $content ? $content->text_ar : $request->old('text_ar'), null, '', 'col-md-12 right-to-left'),

                        $this->drawHtml('image', 'Image', 'image', $content ? $content->image : $request->old('text'), null, '', 'col-md-12'),
                    ],
                ],

                [
                    'wrapper-class' => 'col-md-6',
                    'class' => 'box-default',
                    'box-header' => 'Links',
                    'form_fields' => [
                        $this->drawHtml('small_text', 'Email', 'email', $media ? $media->email : $request->old('email'), null, '', 'col-md-12'),
                        $this->drawHtml('small_text', 'Facebook', 'facebook', $media ? $media->facebook : $request->old('facebook'), null, '', 'col-md-12'),
                        $this->drawHtml('small_text', 'Instagram', 'instagram', $media ? $media->instagram : $request->old('instagram'), null, '', 'col-md-12'),
                        $this->drawHtml('small_text', 'Youtube', 'youtube', $media ? $media->youtube : $request->old('youtube'), null, '', 'col-md-12'),
                        $this->drawHtml('small_text', 'LinkedIn', 'linkedIn', $media ? (isset($media->linkedIn) ? $media->linkedIn : null): $request->old('linkedIn'), null, '', 'col-md-12'),
                        $this->drawHtml('small_text', 'Twitter', 'twitter', $media ? (isset($media->twitter) ? $media->twitter : null): $request->old('twitter'), null, '', 'col-md-12'),

                    ],
                ],
            ]
        ]);
    }

    public function aboutUsUpdate(Request $request)
    {
        $content = Content::where('category', 'about-us-content')->first();
        $media = Content::where('category', 'about-us-social-media')->first();

        if(!$content){
            $content = new Content;
            $content->category = 'about-us-content';
        }

        if(!$media){
            $media = new Content;
            $media->category = 'about-us-social-media';
        }

        $content->text = request('text');
        $content->text_ar = request('text_ar');

        if(request('image')){
            $this->removeFile($content->image);
            $content->image = $this->moveFile(request('image'), 'images/content');
        }

        $links = [
            'email' => request('email'),
            'facebook' => request('facebook'),
            'instagram' => request('instagram'),
            'youtube' => request('youtube'),
            'linkedIn' => request('linkedIn'),
            'twitter' => request('twitter'),
        ];

        $media->text = json_encode($links);

        $content->save();
        $media->save();

        return back()->with('message', 'Content Updated Successfully');
    }
}
