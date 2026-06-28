<?php

namespace App\Http\Controllers\Admin;

use DataTables;
use App\Volunteer;
use Illuminate\Http\Request;
use App\Http\Traits\FormTrait;
use App\Http\Traits\FileTrait;
use App\Http\Controllers\Controller;

class VolunteersController extends Controller
{
    use FormTrait;
    use FileTrait;

    public function index(Request $request)
    {

        if($request->ajax()) {


            $data = Volunteer::latest();


            return DataTables::of($data)


                ->addColumn('my_image', function($row){
                    return $this->drawImage('images/volunteers/' . $row->image, '50');
                })

                ->addColumn('title_en', function($row){
                    return $row->getTranslation('title', 'en');
                })

                ->addColumn('users', function($row){
                    return "<a href='" . route('admin.volunteerUsers.index').'?volunteer_id='. $row->id ."'>Users</a>";
                })


                ->addColumn('action', function($row){
                    return "<a class='edit-link' href='" . route('admin.volunteers.edit', $row->id) . "'>".
                        '<i class="fa fa-edit" aria-hidden="true"></i></a>'.
                        "<a data-toggle='modal' class='delete-link' href='#deleteModal' id='" .route('admin.volunteers.destroy', $row->id) . "'>".
                        "<i class='fa fa-trash' style='color: red;' aria-hidden='true'></i>";
                })
                ->rawColumns(['id', 'title_en', 'my_image', 'users','action'])
                ->make(true);
        }


        return view('components.table_ajax')->with([
            'layout'    => 'layouts.cms',
            'pageTitle'	=> 'Volunteers',
            'table_title' => '',
            'slug'		=> 'volunteer',
            'custom_btn' => "<a href='" . route('admin.volunteers.create') ."' class='btn btn-primary'>Add Volunteer</a>",
            'headers'	=> ['id', 'Name', 'Image',  'Users', 'Action'],
            'action' => route('admin.volunteers.index'),
            'columns' => json_encode([
                ['data' => 'id', 'name' => 'id'],
                ['data' =>  'title_en', 'name'=> 'title'],
                ['data' =>  'my_image', 'name'=> 'my_image', 'searchable' => false, 'sortable' => false],
                ['data' =>  'users', 'name'=> 'users'],
                ['data' => 'action', 'name' => 'action', 'searchable' => false, 'sortable' => false],
            ]),

        ]);
    }

    public function create(Request $request)
    {
        return view('components.form')->with([
            'layout'         => 'layouts.cms',
            'pageTitle'		=> 'Add Volunteer',
            'method'		=> 'post',
            'form_action'	=> route('admin.volunteers.store'),

            'boxes' => [
                [
                    'wrapper-class' => 'col-md-6',
                    'class' => 'box-default',
                    'box-header' => 'Info',
                    'form_fields' => [
                        $this->drawHtml('small_text', 'Title', 'title', $request->old('title') , null, '', 'col-md-12 required'),
                        $this->drawHtml('small_text', 'Title(Arabic)', 'title_ar', $request->old('title_ar') , null, '', 'col-md-12 right-to-left required'),


                        $this->drawHtml('text', 'Details', 'text', $request->old('text'), null, '', 'col-md-12 no-ck required'),
                        $this->drawHtml('text', 'Details(Arabic)', 'text_ar', $request->old('text_ar'), null, '', 'col-md-12 no-ck right-to-left required'),


                        $this->drawHtml('image', 'Image', 'image', null, null, '', 'col-md-12 required'),


                    ],
                ],

            ]
        ]);
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'image' => 'nullable|image:max:700',
        ]);

        $volunteer = new Volunteer();
        $volunteer->setTranslations('title', [
            'en' => request('title'),
            'ar' => request('title_ar'),
        ]);

        $volunteer->setTranslations('text', [
            'en' => request('text'),
            'ar' => request('text_ar'),
        ]);

        if(request('image'))
            $volunteer->image = $this->moveFile(request('image'), 'images/volunteers');

        $volunteer->save();

        return redirect()->route('admin.volunteers.index')->with('message', 'Volunteer created');
    }

    public function edit($id)
    {
        $volunteer = Volunteer::find($id);

        return view('components.form')->with([
            'layout'         => 'layouts.cms',
            'pageTitle'		=> 'Add Volunteer',
            'method'		=> 'update',
            'form_action'	=> route('admin.volunteers.update', $id),

            'boxes' => [
                [
                    'wrapper-class' => 'col-md-6',
                    'class' => 'box-default',
                    'box-header' => 'Info',
                    'form_fields' => [
                        $this->drawHtml('small_text', 'Title', 'title', $volunteer->getTranslation('title', 'en') , null, '', 'col-md-12 required'),
                        $this->drawHtml('small_text', 'Title(Arabic)', 'title_ar', $volunteer->getTranslation('title', 'ar') , null, '', 'col-md-12 right-to-left required'),

                        $this->drawHtml('text', 'Details', 'text', $volunteer->getTranslation('text', 'en'), null, '', 'col-md-12 no-ck required'),
                        $this->drawHtml('text', 'Details(Arabic)', 'text_ar', $volunteer->getTranslation('text', 'ar'), null, '', 'col-md-12 no-ck right-to-left required'),

                        $this->drawHtml('image', 'Image', 'image', $volunteer->image, null, '', 'col-md-12'),


                    ],
                ],

            ]
        ]);
    }

    public function update($id, Request $request)
    {
        $volunteer = Volunteer::find($id);
        $volunteer->setTranslations('title', [
            'en' => request('title'),
            'ar' => request('title_ar'),
        ]);

        $volunteer->setTranslations('text', [
            'en' => request('text'),
            'ar' => request('text_ar'),
        ]);

        if(request('image')){
            $this->removeFile($volunteer->image);
            $volunteer->image = $this->moveFile(request('image'), 'images/volunteers');

        }

        $volunteer->save();

        return redirect()->route('admin.volunteers.index')->with('message', 'Volunteer created');
    }

    public function destroy($id)
    {
        $volunteer = Volunteer::find($id);
        $this->removeFile($volunteer->image);

        $volunteer->delete();

        return back()->with('message', 'Volunteer deleted');
    }
}
