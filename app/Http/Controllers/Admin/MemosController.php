<?php

namespace App\Http\Controllers\Admin;

use DB;
use App\Memo;
use App\Group;
use DataTables;
use App\AppUser;
use App\MemoFiles;
use App\Events\NewItem;
use Illuminate\Http\Request;
use App\Http\Traits\FormTrait;
use App\Http\Traits\FileTrait;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use App\Http\Repositories\FpmApisRepository;
use App\Http\Repositories\PushNotificationsRepository;


class MemosController extends Controller
{

    use FormTrait;
    use FileTrait;


    public function index(Request $request)
    {
        if($request->ajax()) {


            $data = Memo::latest();

            return DataTables::of($data)

                ->addColumn('name_en', function($row){
                    return $row->getTranslation('name', 'en');
                })

                ->addColumn('action', function($row){
                    return "<a class='edit-link' href='" . route('admin.memos.edit', $row->id) . "'>".
                        '<i class="fa fa-edit" aria-hidden="true"></i></a>'.
                        "<a data-toggle='modal' class='delete-link' href='#deleteModal' id='" .route('admin.memos.destroy', $row->id) . "'>".
                        "<i class='fa fa-trash' style='color: red;' aria-hidden='true'></i>";
                })
                ->rawColumns(['id', 'name', 'name_en', 'date', 'action'])
                ->make(true);
        }


        return view('components.table_ajax')->with([
            'layout'    => 'layouts.cms',
            'pageTitle'	=> 'Faqs',
            'table_title' => '',
            'slug'		=> 'Faq',
            'custom_btn' => "<a href='" . route('admin.memos.create') ."' class='btn btn-primary'>Add Memo</a>",
            'headers'	=> ['id', 'Name', 'Date', 'Action'],
            'action' => route('admin.memos.index'),
            'columns' => json_encode([
                ['data' => 'id', 'name' => 'id'],
                ['data' =>  'name_en', 'name'=> 'name'],
                ['data' =>  'date', 'name'=> 'date'],
                ['data' => 'action', 'name' => 'action', 'searchable' => false, 'sortable' => false],
            ]),

        ]);
    }

    public function create(Request $request)
    {
        $groups = Group::all()->pluck('name', 'group_id')->toArray();

        return view('components.form')->with([
            'layout'         => 'layouts.cms',
            'pageTitle'		=> 'Add Memo',
            'method'		=> 'post',
            'form_action'	=> route('admin.memos.store'),

            'boxes' => [
                [
                    'wrapper-class' => 'col-md-6',
                    'class' => 'box-default',
                    'box-header' => 'Info',
                    'form_fields' => [
                        $this->drawHtml('small_text', 'Title (English)', 'name', $request->old('name') , null, '', 'col-md-12 '),
                        $this->drawHtml('small_text', 'Title (Arabic)', 'name_ar', $request->old('name_ar') , null, '', 'col-md-12 right-to-left'),

                        //$this->drawHtml('text', 'Details', 'details', $request->old('details'), null, '', 'col-md-12'),
                        $this->drawHtml('date-picker', 'Date', 'date', $request->old('date'), null, '', 'col-md-12'),
                        //$this->drawHtml('multiple-file-upload', 'Images', 'images',  null, ['add' => route('admin.memos.upload_file'), 'delete' => route('admin.memos.remove_file')],'', 'col-md-12'),

                        $this->drawHtml('pdf', 'PDF file', 'file',  null, null,'', 'col-md-12 required'),

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
                ],


            ]
        ]);
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'name' => 'required',
            //'details' => 'required',
            'date' => 'required',
            'file' => 'required',
            'groups' => 'required_without:all_groups',
        ]);

        $file = $request->file('file');


        //check if file pdf
        if ($file->getClientMimeType() !== 'application/pdf')
        {
            return back()->withErrors('Invalid File Type, File must be PDF.');
        }


        $memo = new Memo();
        $memo->setTranslations('name', [
            'en' => request('name'),
            'ar' => request('name_ar'),
        ]);

        //$memo->details = request('details');
        $memo->date = request('date');
        $memo->file = $this->moveFile(request('file'), 'images/memos');
        $memo->save();

        if(request('all_groups')){
            $groups = Group::all()->pluck('group_id')->toArray();
            $request->merge(['groups' => $groups]);
        }

        $memo->groups()->sync(request('groups'));

        //push notification
        if(request('push_notification')){
            $request->request->add([
                'title' => request('name'),
                'title_ar' => request('name_ar'),
                'text' => request('name'),
                'text_ar' => request('name_ar')
            ]);

            event(new NewItem($request));
        }

        return redirect()->route('admin.memos.index')->with('message', 'Memo created successfully');
    }

    public function edit($id)
    {
        $groups = Group::all()->pluck('name', 'group_id')->toArray();

        $memo = Memo::find($id);
        $default_groups = !$memo->groups()->get()->isempty() ? $memo->groups()->get()->pluck('group_id')->toArray() : '';


        return view('components.form')->with([
            'layout'         => 'layouts.cms',
            'pageTitle'		=> 'Add Memo',
            'method'		=> 'update',
            'form_action'	=> route('admin.memos.update', $id),

            'boxes' => [
                [
                    'wrapper-class' => 'col-md-6',
                    'class' => 'box-default',
                    'box-header' => 'Info',
                    'form_fields' => [
                        $this->drawHtml('small_text', 'Title (English)', 'name', $memo->getTranslation('name', 'en') , null, '', 'col-md-12 required'),
                        $this->drawHtml('small_text', 'Title (Arabic)', 'name_ar', $memo->getTranslation('name', 'ar') , null, '', 'col-md-12 right-to-left required'),

                        //$this->drawHtml('text', 'Details', 'details', $memo->details, null, '', 'col-md-12'),
                        $this->drawHtml('date-picker', 'Date', 'date', $memo->date, null, '', 'col-md-12'),

                        $this->drawHtml('pdf', 'PDF file', 'file',  $memo->file, null,'', 'col-md-12'),
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
            //'details' => 'required',
            //'date' => 'required',
            'groups' => 'required_without:all_groups',

        ]);

        $file = $request->file('file');

        if($file){
            //check if file pdf
            if ($file->getClientMimeType() !== 'application/pdf')
            {
                return back()->withErrors('Invalid File Type, File must be PDF.');
            }
        }



        $memo = Memo::find($id);
        $memo->setTranslations('name', [
            'en' => request('name'),
            'ar' => request('name_ar'),
        ]);
        //$memo->details = request('details');
        $memo->date = request('date');

        if(request('file'))
            $memo->file = $this->moveFile(request('file'), 'images/memos');

        $memo->save();


        if(request('all_groups')){
            $groups = Group::all()->pluck('group_id')->toArray();
            $request->merge(['groups' => $groups]);
        }

        $memo->groups()->sync(request('groups'));

        return redirect()->route('admin.memos.index')->with('message', 'Memo Updated Successfully');
    }

    public function destroy($id)
    {
        $memo = Memo::find($id);
        $this->removeFile($memo->file);
        $memo->delete();

        return back()->with('message', 'Memo deleted successfully');
    }

}
