<?php

namespace App\Http\Controllers\Admin;

use DB;
use App\V2\InternalProcess;
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

class InternalProcessController extends Controller
{
    use FormTrait;
    use FileTrait;
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        if ($request->ajax()) {


            $data = InternalProcess::latest();

            return DataTables::of($data)

                ->addColumn('name_en', function ($row) {
                    return $row->getTranslation('name', 'en');
                })

                ->addColumn('action', function ($row) {
                    return "<a class='edit-link' href='" . route('admin.internal-processes.edit', $row->id) . "'>" .
                        '<i class="fa fa-edit" aria-hidden="true"></i></a>' .
                        "<a data-toggle='modal' class='delete-link' href='#deleteModal' id='" . route('admin.internal-processes.destroy', $row->id) . "'>" .
                        "<i class='fa fa-trash' style='color: red;' aria-hidden='true'></i>";
                })
                ->rawColumns(['id', 'name', 'name_en', 'action'])
                ->make(true);
        }


        return view('components.table_ajax')->with([
            'layout'    => 'layouts.cms',
            'pageTitle'    => 'Internal Process',
            'table_title' => '',
            'slug'        => 'Internal Process',
            'custom_btn' => "<a href='" . route('admin.internal-processes.create') . "' class='btn btn-primary'>Add Internal Process</a>",
            'headers'    => ['id', 'Name', 'Action'],
            'action' => route('admin.internal-processes.index'),
            'columns' => json_encode([
                ['data' => 'id', 'name' => 'id'],
                ['data' =>  'name_en', 'name' => 'name'],
                ['data' => 'action', 'name' => 'action', 'searchable' => false, 'sortable' => false],
            ]),

        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(request $request)
    {
        $groups = Group::all()->pluck('name', 'group_id')->toArray();

        return view('components.form')->with([
            'layout'         => 'layouts.cms',
            'pageTitle'        => 'Add Internal Process',
            'method'        => 'post',
            'form_action'    => route('admin.internal-processes.store'),
            'boxes' => [
                [
                    'wrapper-class' => 'col-md-6',
                    'class' => 'box-default',
                    'box-header' => 'Info',
                    'form_fields' => [
                        $this->drawHtml('small_text', 'Title (English)', 'name', $request->old('name'), null, '', 'col-md-12 '),
                        $this->drawHtml('small_text', 'Title (Arabic)', 'name_ar', $request->old('name_ar'), null, '', 'col-md-12 right-to-left'),

                        $this->drawHtml('text', 'Description (English)', 'description', $request->old('description'), null, '', 'col-md-12'),
                        $this->drawHtml('text', 'Description (Arabic)', 'description_ar', $request->old('description'), null, '', 'col-md-12'),

                        $this->drawHtml('small_text', 'Link', 'link', $request->old('link'), null, '', 'col-md-12'),
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

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $this->validate($request, [
            'name' => 'required',
            'description' => 'required',
            'link' => 'required',
            'groups' => 'required_without:all_groups',
        ]);

            $internal_process = new InternalProcess();
            $internal_process->setTranslations('name', [
                'en' => request('name'),
                'ar' => request('name_ar'),
            ]);
            $internal_process->setTranslations('description', [
                'en' => request('description'),
                'ar' => request('description_ar'),
            ]);
            $internal_process->link = request('link');
            $internal_process->save();

        

        if (request('all_groups')) {
            $groups = Group::all()->pluck('group_id')->toArray();
            $request->merge(['groups' => $groups]);
        }

        $internal_process->groups()->sync(request('groups'));

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

        return redirect()->route('admin.internal-processes.index')->with('message', 'Internal process created successfully');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $groups = Group::all()->pluck('name', 'group_id')->toArray();

        $law = InternalProcess::find($id);
        $default_groups = !$law->groups()->get()->isempty() ? $law->groups()->get()->pluck('group_id')->toArray() : '';


        return view('components.form')->with([
            'layout'         => 'layouts.cms',
            'pageTitle'        => 'Edit Internal process',
            'method'        => 'update',
            'form_action'    => route('admin.internal-processes.update', $id),

            'boxes' => [
                [
                    'wrapper-class' => 'col-md-6',
                    'class' => 'box-default',
                    'box-header' => 'Info',
                    'form_fields' => [
                        $this->drawHtml('small_text', 'Title (English)', 'name', $law->getTranslation('name', 'en'), null, '', 'col-md-12 required'),
                        $this->drawHtml('small_text', 'Title (Arabic)', 'name_ar', $law->getTranslation('name', 'ar'), null, '', 'col-md-12 right-to-left required'),

                        $this->drawHtml('text', 'Description (English)', 'description', $law->getTranslation('description', 'en'), null, '', 'col-md-12 required'),
                        $this->drawHtml('text', 'Description (Arabic)', 'description_ar', $law->getTranslation('description', 'ar'), null, '', 'col-md-12 right-to-left required'),

                        $this->drawHtml('small_text', 'Link', 'link', $law->link, null, '', 'col-md-12'),
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

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $this->validate($request, [
            'name' => 'required',
            // 'description' => 'required',
            'link' => 'required',
            'groups' => 'required_without:all_groups',
        ]);
        $law = InternalProcess::find($id);
        $law->setTranslations('name', [
            'en' => request('name'),
            'ar' => request('name_ar'),
        ]);
        $law->setTranslations('description', [
            'en' => request('description'),
            'ar' => request('description_ar'),
        ]);
        $law->link = request('link');
        $law->save();

        if (request('all_groups')) {
            $groups = Group::all()->pluck('group_id')->toArray();
            $request->merge(['groups' => $groups]);
        }

        $law->groups()->sync(request('groups'));

        return redirect()->route('admin.internal-processes.index')->with('message', 'Internal process Updated Successfully');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $law = InternalProcess::find($id);
        $law->delete();

        return back()->with('message', 'Internal process deleted successfully');
    }
}
