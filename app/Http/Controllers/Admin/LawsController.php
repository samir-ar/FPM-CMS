<?php

namespace App\Http\Controllers\Admin;

use DB;
use App\V2\Law;
use App\Group;
use DataTables;
use App\AppUser;
use App\Events\NewItem;
use Illuminate\Http\Request;
use App\Http\Traits\FormTrait;
use App\Http\Traits\FileTrait;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use App\Http\Repositories\FpmApisRepository;
use App\Http\Repositories\PushNotificationsRepository;
use Log;

class LawsController extends Controller
{
    use FormTrait;
    use FileTrait;


    public function index(Request $request)
    {
        if ($request->ajax()) {


            $data = Law::latest();

            return DataTables::of($data)

                ->addColumn('name_en', function ($row) {
                    return $row->getTranslation('name', 'en');
                })

                ->addColumn('action', function ($row) {
                    return "<a class='edit-link' href='" . route('admin.laws.edit', $row->id) . "'>" .
                        '<i class="fa fa-edit" aria-hidden="true"></i></a>' .
                        "<a data-toggle='modal' class='delete-link' href='#deleteModal' id='" . route('admin.laws.destroy', $row->id) . "'>" .
                        "<i class='fa fa-trash' style='color: red;' aria-hidden='true'></i>";
                })
                ->rawColumns(['id', 'name', 'name_en', 'status', 'date', 'action'])
                ->make(true);
        }


        return view('components.table_ajax')->with([
            'layout'    => 'layouts.cms',
            'pageTitle'    => 'Laws',
            'table_title' => '',
            'slug'        => 'Laws',
            'custom_btn' => "<a href='" . route('admin.laws.create') . "' class='btn btn-primary'>Add Law</a>",
            'headers'    => ['id', 'Name', 'Status', 'Date', 'Action'],
            'action' => route('admin.laws.index'),
            'columns' => json_encode([
                ['data' => 'id', 'name' => 'id'],
                ['data' =>  'name_en', 'name' => 'name'],
                ['data' =>  'status', 'name' => 'status'],
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
            'pageTitle'        => 'Add Law',
            'method'        => 'post',
            'form_action'    => route('admin.laws.store'),

            'boxes' => [
                [
                    'wrapper-class' => 'col-md-6',
                    'class' => 'box-default',
                    'box-header' => 'Info',
                    'form_fields' => [
                        $this->drawHtml('small_text', 'Title (English)', 'name', $request->old('name'), null, '', 'col-md-12 '),
                        $this->drawHtml('small_text', 'Title (Arabic)', 'name_ar', $request->old('name_ar'), null, '', 'col-md-12 right-to-left'),

                        $this->drawHtml('select-box', 'Status', 'status', $request->old('status'), [NULL => 'Select Status', 'Approved' => 'Approved', 'Pending' => 'Pending'], '', 'col-md-12'),
                        $this->drawHtml('text', 'Details', 'details', $request->old('details'), null, '', 'col-md-12'),

                        $this->drawHtml('date-picker', 'Date', 'date', $request->old('date'), null, '', 'col-md-12'),
                        //$this->drawHtml('multiple-file-upload', 'Images', 'images',  null, ['add' => route('admin.laws.upload_file'), 'delete' => route('admin.laws.remove_file')],'', 'col-md-12'),

                        $this->drawHtml('pdf', 'PDF file', 'file',  null, null, '', 'col-md-12'),

                    ],
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
                ],


            ]
        ]);
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'name' => 'required',
            'details' => 'required',
            'status' => 'required',
            'date' => 'required',
            // 'file' => 'required',
            'groups' => 'required_without:all_groups',
        ]);

        

        if($request->file == null)
        {
            $law = new law();
            $law->setTranslations('name', [
                'en' => request('name'),
                'ar' => request('name_ar'),
            ]);
    
            $law->details = request('details');
            $law->date = request('date');
            $law->status = request('status');
            $law->save();
        }else{
            $file = $request->file('file');

            //check if file pdf
            if ($file->getClientMimeType() !== 'application/pdf') {
                return back()->withErrors('Invalid File Type, File must be PDF.');
            }

            $law = new law();
            $law->setTranslations('name', [
                'en' => request('name'),
                'ar' => request('name_ar'),
            ]);
    
            $law->details = request('details');
            $law->date = request('date');
            $law->status = request('status');
            $law->file = $this->moveFile(request('file'), 'images/laws');
            $law->save();
        }
        

        if (request('all_groups')) {
            $groups = Group::all()->pluck('group_id')->toArray();
            $request->merge(['groups' => $groups]);
        }

        $law->groups()->sync(request('groups'));

        //push notification
        if (request('push_notification')) {
            $request->request->add([
                'title' => request('name'),
                'title_ar' => request('name_ar'),
                'text' => request('name'),
                'text_ar' => request('name_ar')
            ]);

            event(new NewItem($request));
        }

        return redirect()->route('admin.laws.index')->with('message', 'law created successfully');
    }

    public function edit($id)
    {
        $groups = Group::all()->pluck('name', 'group_id')->toArray();

        $law = Law::find($id);
        $default_groups = !$law->groups()->get()->isempty() ? $law->groups()->get()->pluck('group_id')->toArray() : '';


        return view('components.form')->with([
            'layout'         => 'layouts.cms',
            'pageTitle'        => 'Edit Law',
            'method'        => 'update',
            'form_action'    => route('admin.laws.update', $id),

            'boxes' => [
                [
                    'wrapper-class' => 'col-md-6',
                    'class' => 'box-default',
                    'box-header' => 'Info',
                    'form_fields' => [
                        $this->drawHtml('small_text', 'Title (English)', 'name', $law->getTranslation('name', 'en'), null, '', 'col-md-12 required'),
                        $this->drawHtml('small_text', 'Title (Arabic)', 'name_ar', $law->getTranslation('name', 'ar'), null, '', 'col-md-12 right-to-left required'),
                        $this->drawHtml('select-box', 'Status', 'status', $law->status, [NULL => 'Select Status', 'Approved' => 'Approved', 'Pending' => 'Pending'], '', 'col-md-12'),

                        $this->drawHtml('text', 'Details', 'details', $law->details, null, '', 'col-md-12'),
                        $this->drawHtml('date-picker', 'Date', 'date', $law->date, null, '', 'col-md-12'),

                        $this->drawHtml('pdf', 'PDF file', 'file',  $law->file, null, '', 'col-md-12'),
                    ],
                ],

                [
                    'wrapper-class' => 'col-md-6',
                    'class' => 'box-primary',
                    'box-header' => 'Permissions',
                    'form_fields' => [
                        $this->drawHtml('multiple-select-box', 'Groups', 'groups[]', $default_groups, $groups, '', 'col-md-12 '),
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

        if ($file) {
            //check if file pdf
            if ($file->getClientMimeType() !== 'application/pdf') {
                return back()->withErrors('Invalid File Type, File must be PDF.');
            }
        }



        $law = Law::find($id);
        $law->setTranslations('name', [
            'en' => request('name'),
            'ar' => request('name_ar'),
        ]);
        $law->details = request('details');
        $law->status = request('status');
        $law->date = request('date');

        if (request('file'))
            $law->file = $this->moveFile(request('file'), 'images/laws');

        $law->save();


        if (request('all_groups')) {
            $groups = Group::all()->pluck('group_id')->toArray();
            $request->merge(['groups' => $groups]);
        }

        $law->groups()->sync(request('groups'));

        return redirect()->route('admin.laws.index')->with('message', 'law Updated Successfully');
    }

    public function destroy($id)
    {
        $law = Law::find($id);
        $this->removeFile($law->file);
        $law->delete();

        return back()->with('message', 'law deleted successfully');
    }
}
