<?php

namespace App\Http\Controllers\Admin;

use DataTables;
use App\V2\Person;
use App\V2\DynamicRepresentative;
use App\V2\RepresentativePosition;
use App\Http\Traits\FormTrait;
use App\Http\Traits\FileTrait;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class RepresentativesController extends Controller
{
    use FormTrait;
    use FileTrait;

    public function index(Request $request)
    {
        if($request->ajax()) {

            $data = Person::with(['dynamicRepresentative','position'])->get();

            return DataTables::of($data)

                ->addColumn('name', function($row){
                    return $row->name;
                })

                ->addColumn('category', function($row){
                    if(!$row->dynamicRepresentative) return "N/A";
                    return $row->dynamicRepresentative->title;
                })

                ->addColumn('id', function($row){
                    return $row->id;
                })

                ->addColumn('image', function($row){
                    return $this->drawImage('images/representatives/' . $row->image, '100');
                })

                ->addColumn('position', function($row){
                    if(!$row->position) return "N/A";
                    return $row->position->name;
                })

                ->addColumn('action', function($row){
                    return "<a class='edit-link' href='" . route('admin.representatives.edit', $row->id) . "'>".
                        '<i class="fa fa-edit" aria-hidden="true"></i></a>'.
                        "<a data-toggle='modal' class='delete-link' href='#deleteModal' id='" .route('admin.representatives.destroy', $row->id) . "'>".
                        "<i class='fa fa-trash' style='color: red;' aria-hidden='true'></i>";
                })

                ->escapeColumns('image')
                ->make(true);
        }


        return view('components.table_ajax')->with([
            'layout'    => 'layouts.cms',
            'pageTitle'	=> 'Representatives',
            'table_title' => '',
            'slug'		=> 'representative',
            'custom_btn' => "<a href='" . route('admin.representatives.create') ."' class='btn btn-primary'>Add Representative</a>",
            'headers'	=> ['id', 'Name', 'Category', 'Image', 'Order','Position','Action'],
            'action' => route('admin.representatives.index'),
            'columns' => json_encode([
                ['data' => 'id', 'name' => 'id'],
                ['data' =>  'name', 'name'=> 'name'],
                ['data' =>  'category', 'name'=> 'category'],
                ['data' =>  'image', 'name'=> 'image', 'searchable' => false, 'sortable' => false],
                ['data' =>  'order', 'name'=> 'order'],
                ['data' =>  'position', 'name'=> 'position'],
                ['data' => 'action', 'name' => 'action', 'searchable' => false, 'sortable' => false],
            ]),

        ]);
    }

    public function create(Request $request)
    {
        return view('components.form')->with([
            'layout'         => 'layouts.cms',
            'pageTitle'		=> 'Add Representative',
            'method'		=> 'post',
            'form_action'	=> route('admin.representatives.store'),

            'boxes' => [
                [
                    'wrapper-class' => 'col-md-12',
                    'class' => 'box-default',
                    'box-header' => 'Info',
                    'form_fields' => [
                        $this->drawHtml('small_text', 'Name(English)', 'name', $request->old('name') , null, '', 'col-md-6 required'),
                        $this->drawHtml('small_text', 'Name(Arabic)', 'name_ar', $request->old('name_ar') , null, '', 'col-md-6 right-to-left required'),

                        $this->drawHtml('select-box', 'Category', 'category', '', DynamicRepresentative::all()->pluck('title','id'), '', 'col-md-12 right-to-left required'),
                        $this->drawHtml('select-box', 'Position', 'position_id', '', RepresentativePosition::all()->pluck('name','id'), '', 'col-md-12 right-to-left required'),

                        $this->drawHtml('image', 'Image', 'image', null, null, '', 'col-md-12 required'),

                        $this->drawHtml('number', 'Order', 'order', $request->old('order') , null, '', 'col-md-12'),
                        // $this->drawHtml('small_text', 'Type', 'type', $request->old('type') , null, 'keep it empty', 'col-md-12'),
                        $this->drawHtml('select-box', 'Type', 'type', $request->old('type'), [NULL => 'Select Status','Founder' => 'Founder','President' => 'President','Representative' => 'Representative'], '', 'col-md-12'),


                    ],
                ],
            ]
        ]);
    }

    public function store(Request $request)
    {

        $this->validate($request, [
            'name' => 'required',
            'name_ar' => 'required',
            'category' => 'required',
            'image' => 'required|max:700',
            // 'type' => 'unique:persons',
        ]);

        $person = new Person();

        $person->setTranslations('name', [
            'en' => request('name'),
            'ar' => request('name_ar'),
        ]);

        $person->dynamic_representative_id = request('category');

        $person->image = $this->moveFile(request('image'), 'images/representatives');


        $person->order = request('order');
        $person->type = request('type');

        if(request('type') == 'President'){
            $person->rep_order = 1;
        }elseif(request('type') == 'Founder'){
            $person->rep_order = 2;
        }else{
            $person->rep_order = 3;
        }

        if(request('position_id')){
            $person->representative_position_id = request('position_id');
        }

        $person->save();

        return redirect()->route('admin.representatives.index')->with('message', 'Representative Added Successfully');
    }

    public function edit($id)
    {
        $person = Person::find($id);

        return view('components.form')->with([
            'layout'        => 'layouts.cms',
            'pageTitle'		=> 'Edit Representative',
            'method'		=> 'update',
            'form_action'	=> route('admin.representatives.update', $id),

            'boxes' => [
                [
                    'wrapper-class' => 'col-md-12',
                    'class' => 'box-default',
                    'box-header' => 'Info',
                    'form_fields' => [
                        $this->drawHtml('small_text', 'Name(English)', 'name', $person->getTranslation('name', 'en') , null, '', 'col-md-6 required'),
                        $this->drawHtml('small_text', 'Name(Arabic)', 'name_ar', $person->getTranslation('name', 'ar')  , null, '', 'col-md-6 right-to-left required'),

                        $this->drawHtml('select-box', 'Category', 'category', $person->dynamic_representative_id, DynamicRepresentative::all()->pluck('title','id'), '', 'col-md-12 right-to-left required'),
                        $this->drawHtml('select-box', 'Position', 'position_id', $person->representative_position_id, RepresentativePosition::all()->pluck('name','id'), '', 'col-md-12 right-to-left required'),

                        $this->drawHtml('image', 'Image', 'image', $person->image, null, '', 'col-md-12 '),

                        $this->drawHtml('number', 'Order', 'order', $person->order , null, '', 'col-md-12'),
                        $this->drawHtml('select-box', 'Type', 'type', $person->type, [NULL => 'Select Status','Founder' => 'Founder','President' => 'President'], '', 'col-md-12'),


                    ],
                ],
                ]
            ]);
        }

        public function update($id, Request $request)
        {
            $this->validate($request, [
                'name' => 'required',
                'name_ar' => 'required',
                'category' => 'required',
                'image' => 'nullable|mimes:jpg,png,gif,jepg|max:700',
        ]);

        $person = Person::find($id);

        $person->setTranslations('name', [
            'en' => request('name'),
            'ar' => request('name_ar'),
        ]);

        $person->dynamic_representative_id= request('category');

        if(request('image')){
            $this->removeFile($person->image);
            $person->image = $this->moveFile(request('image'), 'images/representatives');
        }
        if(request('position_id')){
            $person->representative_position_id = request('position_id');
        }
        $person->order = request('order');

        $person->save();

        return redirect()->route('admin.representatives.index')->with('message', 'Representative Updated Successfully');
    }

    public function destroy($id)
    {
        $r = Person::find($id);

        $this->removeFile($r->image);

        $r->delete();

        return back()->with('message', 'Representatives Deleted successfully');
    }
}
