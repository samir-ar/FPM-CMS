<?php

namespace App\Http\Controllers\Admin;

use App\Placeholder;
use Illuminate\Http\Request;
use App\Http\Traits\FormTrait;
use App\Http\Traits\FileTrait;
use App\Http\Controllers\Controller;

class PlaceholdersController extends Controller
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
            'table_btns' => "<a href='" . route('admin.placeholders.create') . "' class='btn btn-primary'>Add Placeholder</a>",
            'headers'	=> ['id', 'Type', 'Image', 'Action'],
            'rows'		=> Placeholder::all()->map(function($r){
                return[
                    $r->id,
                    $r->type,
                    $this->drawImage('images/placeholders/' . $r->image, '100'),
                    "<a class='edit-link' href='" . route('admin.placeholders.edit', $r->id) . "'>".
                    "<i class='fa fa-edit'></i>".
                    "<a data-toggle='modal' class='delete-link' href='#deleteModal' id='" .route('admin.placeholders.destroy', $r->id) . "'>".
                    "<i class='fa fa-trash' style='color: red;' aria-hidden='true'></i>",
                ];
            })
        ]);
    }

    public function create(Request $request)
    {
        return view('components.form')->with([
            'layout'         => 'layouts.cms',
            'pageTitle'		=> 'Add Placeholder',
            'method'		=> 'post',
            'form_action'	=> route('admin.placeholders.store'),

            'boxes' => [
                [
                    'wrapper-class' => 'col-md-6',
                    'class' => 'box-default',
                    'box-header' => 'Info',
                    'form_fields' => [
                        $this->drawHtml('select-box', 'Type', 'type', $request->old('slug') , $this->placeholdersTypes(), '', 'col-md-12 '),
                        $this->drawHtml('image', 'Image', 'image', $request->old('image') , null, '', 'col-md-12'),
                    ],
                ],
            ]
        ]);
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'image'=>'required|max:700',
            'type' => 'required',
        ]);

        $placeholder = new Placeholder();
        $placeholder->type = request('type');
        $placeholder->image = $this->moveFile(request('image'), 'images/placeholders');
        $placeholder->save();

        return redirect()->route('admin.placeholders.index')->with('message', 'Placeholder Created');
    }

    public function edit($id)
    {
        $placeholder = Placeholder::find($id);

        return view('components.form')->with([
            'layout'         => 'layouts.cms',
            'pageTitle'		=> 'Edit Placeholder',
            'method'		=> 'update',
            'form_action'	=> route('admin.placeholders.update', $id),

            'boxes' => [
                [
                    'wrapper-class' => 'col-md-6',
                    'class' => 'box-default',
                    'box-header' => 'Info',
                    'form_fields' => [
                        $this->drawHtml('select-box', 'Type', 'type', $placeholder->slug , $this->placeholdersTypes(), '', 'col-md-12 required'),
                        $this->drawHtml('image', 'Image', 'image', $placeholder->image , null, '', 'col-md-12 required'),
                    ],
                ],
            ]
        ]);
    }

    public function update($id, Request $request)
    {

        $this->validate($request, [
            //'image'=>'nullable|image|max:700',
            'type' => 'required',
        ]);

        $placeholder = Placeholder::find($id);

        $placeholder->type = request('type');

        if(request('image'))
            $placeholder->image = $this->moveFile(request('image'), 'images/placeholders');

        $placeholder->save();

        return redirect()->route('admin.placeholders.index')->with('message', 'Placeholder Updated');
    }

    public function destroy($id)
    {
        $placeholder = Placeholder::find($id);

        $this->removeFile($placeholder->image);

        $placeholder->delete();

        return back()->with('message', 'Placeholder Deleted');
    }

    private function placeholdersTypes()
    {
        return [
            'news' => 'News',
            'messages' => 'Messages',
            'pdf_news' => 'Pdf'
        ];
    }
}
