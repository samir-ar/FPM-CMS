<?php

namespace App\Http\Controllers\Admin;

use App\Events\NewItem;
use App\Group;
use App\Link;
use DataTables;
use Illuminate\Http\Request;
use App\Http\Traits\FormTrait;
use App\Http\Traits\FileTrait;
use App\Http\Controllers\Controller;

class LinksController extends Controller
{
    use FormTrait;
    use FileTrait;

    public function index(Request $request)
    {
        if($request->ajax()) {


            $data = Link::orderBy('order', 'asc')->orderBy('created_at', 'desc')->latest();

            return DataTables::of($data)
                ->addColumn('my_public', function($row){

                    return $row->public ? 'true' : 'false';
                })

                ->addColumn('my_name', function($row){
                    return $row->getTranslation('name', 'en');
                })

                ->addColumn('action', function($row){
                    return "<a class='edit-link' href='" . route('admin.links.edit', $row->id) . "'>".
                        '<i class="fa fa-edit" aria-hidden="true"></i></a>'.
                        "<a data-toggle='modal' class='delete-link' href='#deleteModal' id='" .route('admin.links.destroy', $row->id) . "'>".
                        "<i class='fa fa-trash' style='color: red;' aria-hidden='true'></i>";
                })
                ->rawColumns(['id', 'name', 'my_name', 'link', 'order', 'my_public', 'created_at','action'])
                ->make(true);
        }


        return view('components.table_ajax')->with([
            'layout'    => 'layouts.cms',
            'pageTitle'	=> 'Important Links',
            'table_title' => '',
            'slug'		=> 'link',
            'custom_btn' => "<a href='" . route('admin.links.create') ."' class='btn btn-primary'>Add Link</a>",
            'headers'	=> ['id', 'Name', 'Link', 'Order', 'Public', 'Date', 'Action'],
            'action' => route('admin.links.index'),
            'columns' => json_encode([
                ['data' => 'id', 'name' => 'id'],
                ['data' =>  'my_name', 'name'=> 'name'],
                ['data' =>  'link', 'name'=> 'link'],
                ['data' =>  'order', 'name'=> 'order'],
                ['data' =>  'my_public', 'name'=> 'my_public'],
                ['data' =>  'created_at', 'name'=> 'created_at'],
                ['data' => 'action', 'name' => 'action', 'searchable' => false, 'sortable' => false],
            ]),

        ]);
    }

    public function create(Request $request)
    {
        $groups = Group::all()->pluck('name', 'group_id')->toArray();

        return view('components.form')->with([
            'layout'         => 'layouts.cms',
            'pageTitle'		=> 'Add Link',
            'method'		=> 'post',
            'form_action'	=> route('admin.links.store'),

            'boxes' => [
                [
                    'wrapper-class' => 'col-md-6',
                    'class' => 'box-default',
                    'box-header' => 'Info',
                    'form_fields' => [
                        $this->drawHtml('small_text', 'Name(English)', 'name', $request->old('name') , null, '', 'col-md-12 required'),
                        $this->drawHtml('small_text', 'Name(Arabic)', 'name_ar', $request->old('name_ar') , null, '', 'col-md-12 required'),

                        $this->drawHtml('small_text', 'Link', 'link', $request->old('link') , null, '', 'col-md-12 required'),
                        $this->drawHtml('checkbox', 'Public', 'public', $request->old('public'), null, '', 'col-md-12'),
                        $this->drawHtml('number', 'Order', 'order', $request->old('order'), null, '', 'col-md-3'),

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
                ]

            ]
        ]);
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'name' => 'required',
            'link' => 'required',
            'public' => '',
            'order' => '',
        ]);


        $memo = new Link();
        $memo->setTranslations('name', [
            'en' => request('name'),
            'ar' => request('name_ar'),
        ]);

        $memo->link = request('link');

        if(request('public'))
            $memo->public = true;

        if(request('order'))
            $memo->order = request('order');

        $memo->save();


        if(request('all_groups')){
            $groups = Group::all()->pluck('group_id')->toArray();
            $request->merge(['groups' => $groups]);
        }

        $memo->groups()->sync(request('groups'));

        //push notification
        if(request('push_notification') && request('groups')){
            $request->request->add([
                'title' => request('name'),
                'title_ar' => request('name_ar'),
                'text' => request('name'),
                'text_ar' => request('name_ar'),
                //'image' => request('image'),
            ]);

            event(new NewItem($request));
        }


        return redirect()->route('admin.links.index')->with('message', 'Link created successfully');
    }

    public function edit($id)
    {
        $groups = Group::all()->pluck('name', 'group_id')->toArray();
        $link = Link::find($id);
        $default_groups = !$link->groups()->get()->isempty() ? $link->groups()->get()->pluck('group_id')->toArray() : '';

        return view('components.form')->with([
            'layout'         => 'layouts.cms',
            'pageTitle'		=> 'Update Link',
            'method'		=> 'update',
            'form_action'	=> route('admin.links.update', $id),

            'boxes' => [
                [
                    'wrapper-class' => 'col-md-6',
                    'class' => 'box-default',
                    'box-header' => 'Info',
                    'form_fields' => [
                        $this->drawHtml('small_text', 'Name(English)', 'name', $link->getTranslation('name', 'en') , null, '', 'col-md-12 required'),
                        $this->drawHtml('small_text', 'Name(Arabic)', 'name_ar', $link->getTranslation('name', 'ar') , null, '', 'col-md-12 required'),

                        $this->drawHtml('small_text', 'Link', 'link', $link->link , null, '', 'col-md-12 required'),
                        $this->drawHtml('checkbox', 'Public', 'public', $link->public, null, '', 'col-md-12'),
                        $this->drawHtml('number', 'Order', 'order', $link->order, null, '', 'col-md-3'),

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
            'name' => 'required',
            'link' => 'required',
            'public' => '',
            'order' => '',
        ]);


        $link = Link::find($id);
        $link->setTranslations('name', [
            'en' => request('name'),
            'ar' => request('name_ar'),
        ]);
        $link->link = request('link');

        $link->public = request('public') ? true : false;

        if(request('order'))
            $link->order = request('order');

        $link->save();

        if(request('all_groups')){
            $groups = Group::all()->pluck('group_id')->toArray();
            $request->merge(['groups' => $groups]);
        }

        $link->groups()->sync(request('groups'));


        return redirect()->route('admin.links.index')->with('message', 'Link Updated successfully');
    }

    public function destroy($id)
    {
        Link::find($id)->delete();

        return back()->with('message', 'Link Deleted Successfully');
    }
}
