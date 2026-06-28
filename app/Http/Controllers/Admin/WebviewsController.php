<?php

namespace App\Http\Controllers\Admin;

use App\Webview;
use Illuminate\Http\Request;
use App\Http\Traits\FormTrait;
use App\Http\Traits\FileTrait;
use App\Http\Controllers\Controller;

class WebviewsController extends Controller
{
    use FormTrait;
    use FileTrait;

    public function index(Request $request)
    {
        return view('components.table')->with([
            'layout'    => 'layouts.cms',
            'pageTitle'	=> 'Branches',
            'table_title' => 'branch',
            'slug'		=> 'branch',
            'table_btns' => "<a href='" . route('admin.webviews.create') . "' class='btn btn-primary'>Add Webview</a>",
            'headers'	=> ['id', 'Slug', 'Url', 'Action'],
            'rows'		=> Webview::all()->map(function($r){
                return[
                    $r->id,
                    $r->slug,
                    $r->url,
                    "<a class='edit-link' href='" . route('admin.webviews.edit', $r->id) . "'>".
                    "<i class='fa fa-edit'></i>".
                    "<a data-toggle='modal' class='delete-link' href='#deleteModal' id='" .route('admin.webviews.destroy', $r->id) . "'>".
                    "<i class='fa fa-trash' style='color: red;' aria-hidden='true'></i>",
                ];
            })
        ]);
    }

    public function create(Request $request)
    {
        return view('components.form')->with([
            'layout'         => 'layouts.cms',
            'pageTitle'		=> 'Add Webview',
            'method'		=> 'post',
            'form_action'	=> route('admin.webviews.store'),

            'boxes' => [
                [
                    'wrapper-class' => 'col-md-6',
                    'class' => 'box-default',
                    'box-header' => 'Info',
                    'form_fields' => [
                        $this->drawHtml('small_text', 'Slug', 'slug', $request->old('slug') , null, '', 'col-md-12 '),
                        $this->drawHtml('small_text', 'Url', 'url', $request->old('url') , null, '', 'col-md-12'),
                    ],
                ],
            ]
        ]);
    }

    public function store(Request $request)
    {
        $webview = new Webview();
        $webview->slug = request('slug');
        $webview->url = request('url');

        $webview->save();

        return redirect()->route('admin.webviews.index')->with('message', 'Webview Added');
    }

    public function edit($id)
    {
        $webview = Webview::find($id);

        return view('components.form')->with([
            'layout'         => 'layouts.cms',
            'pageTitle'		=> 'Update Webview',
            'method'		=> 'update',
            'form_action'	=> route('admin.webviews.update', $id),

            'boxes' => [
                [
                    'wrapper-class' => 'col-md-6',
                    'class' => 'box-default',
                    'box-header' => 'Info',
                    'form_fields' => [
                        $this->drawHtml('small_text', 'Slug', 'slug', $webview->slug , null, '', 'col-md-12 '),
                        $this->drawHtml('small_text', 'Url', 'url', $webview->url , null, '', 'col-md-12'),
                    ],
                ],
            ]
        ]);
    }

    public function update($id, Request $request)
    {
        $webview = Webview::find($id);

        $webview->slug = request('slug');
        $webview->url = request('url');

        $webview->save();

        return redirect()->route('admin.webviews.index')->with('message', 'Webview Updated.');
    }

    public function destroy($id)
    {
        Webview::find($id)->delete();

        return back()->with('message', 'Webview Deleted');
    }
}
