<?php

namespace App\Http\Controllers\Admin;

use DB;
use App\V2\Biography;
use App\Group;
use App\V2\Person;
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

class BiographiesController extends Controller
{
    use FormTrait;
    use FileTrait;


    public function index(Request $request)
    {
        if($request->ajax()) {


            $data = Biography::latest();

            return DataTables::of($data)

                ->addColumn('title_en', function($row){
                    return $row->getTranslation('title', 'en');
                })

                ->addColumn('action', function($row){
                    return "<a class='edit-link' href='" . route('admin.biography.edit', $row->id) . "'>".
                        '<i class="fa fa-edit" aria-hidden="true"></i></a>'.
                        "<a data-toggle='modal' class='delete-link' href='#deleteModal' id='" .route('admin.biography.destroy', $row->id) . "'>".
                        "<i class='fa fa-trash' style='color: red;' aria-hidden='true'></i>";
                })
                ->rawColumns(['id', 'title', 'action'])
                ->make(true);
        }


        return view('components.table_ajax')->with([
            'layout'    => 'layouts.cms',
            'pageTitle'	=> 'biography',
            'table_title' => '',
            'slug'		=> 'biography',
            'custom_btn' => "<a href='" . route('admin.biography.create') ."' class='btn btn-primary'>Add biography</a>",
            'headers'	=> ['id', 'Name', 'Action'],
            'action' => route('admin.biography.index'),
            'columns' => json_encode([
                ['data' => 'id', 'name' => 'id'],
                ['data' =>  'title_en', 'name'=> 'title'],
                ['data' => 'action', 'name' => 'action', 'searchable' => false, 'sortable' => false],
            ]),

        ]);
    }

    public function create(Request $request)
    {
        return view('components.form')->with([
            'layout'         => 'layouts.cms',
            'pageTitle'		=> 'Add biography',
            'method'		=> 'post',
            'form_action'	=> route('admin.biography.store'),

            'boxes' => [
                [
                    'wrapper-class' => 'col-md-6',
                    'class' => 'box-default',
                    'box-header' => 'Info',
                    'form_fields' => [
                        $this->drawHtml('small_text', 'Title (English)', 'title', $request->old('title') , null, '', 'col-md-12 '),
                        $this->drawHtml('small_text', 'Title (Arabic)', 'title_ar', $request->old('title_ar') , null, '', 'col-md-12 right-to-left'),
                        $this->drawHtml('text', 'Body (English)', 'body', $request->old('body'), null, '', 'col-md-12'),
                        $this->drawHtml('text', 'Body (Arabic)', 'body_ar', $request->old('body_ar'), null, '', 'col-md-12 right-to-left'),
                        $this->drawHtml('select-box', 'Select Person', 'person_id', '', Person::all()->pluck('name','id'), '', 'col-md-12 required'),
                    ],
                ],
            ]
        ]);
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'title' => 'required',
            'body' => 'required',
        ]);
        $biography = new Biography();
        $biography->setTranslations('title', [
            'en' => request('title'),
            'ar' => request('title_ar'),
        ]);
        $biography->setTranslations('body', [
            'en' => request('body'),
            'ar' => request('body_ar'),
        ]);
        $biography->person_id = request('person_id');
        $biography->save();

        //push notification
        if(request('push_notification')){
            $request->request->add([
                'title' => request('title'),
                'title_ar' => request('title_ar')
            ]);

            event(new NewItem($request));
        }

        return redirect()->route('admin.biography.index')->with('message', 'biography created successfully');
    }

    public function edit($id)
    {
        $biography = Biography::find($id);
        return view('components.form')->with([
            'layout'         => 'layouts.cms',
            'pageTitle'		=> 'Edit biography',
            'method'		=> 'update',
            'form_action'	=> route('admin.biography.update', $id),

            'boxes' => [
                [
                    'wrapper-class' => 'col-md-6',
                    'class' => 'box-default',
                    'box-header' => 'Info',
                    'form_fields' => [
                        $this->drawHtml('small_text', 'Title (English)', 'title', $biography->getTranslation('title','en') , null, '', 'col-md-12 '),
                        $this->drawHtml('small_text', 'Title (Arabic)', 'title_ar', $biography->getTranslation('title', 'ar') , null, '', 'col-md-12 right-to-left'),
                        $this->drawHtml('text', 'Body (English)', 'body', $biography->getTranslation('body', 'en'), null, '', 'col-md-12'),
                        $this->drawHtml('text', 'Body (Arabic)', 'body_ar', $biography->getTranslation('body', 'ar'), null, '', 'col-md-12 right-to-left'),
                        $this->drawHtml('select-box', 'Select Person', 'person_id', $biography->person_id, Person::all()->pluck('name','id'), '', 'col-md-12 required'),
                    ],
                ],
            ]
        ]);
    }

    public function update($id, Request $request)
    {

        $this->validate($request, [
            'title' => 'required',
            'body' => 'required',
            'person_id' => 'required',
        ]);
        $biography = Biography::find($id);
        $biography->setTranslations('title', [
            'en' => request('title'),
            'ar' => request('title_ar'),
        ]);
        $biography->setTranslations('body', [
            'en' => request('body'),
            'ar' => request('body_ar'),
        ]);        
        $biography->person_id = request('person_id');

        $biography->save();


        return redirect()->route('admin.biography.index')->with('message', 'biography Updated Successfully');
    }

    public function destroy($id)
    {
        $biography = Biography::find($id);
        $biography->delete();

        return back()->with('message', 'biography deleted successfully');
    }
}

