<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use DataTables;
use App\V2\Person;
use App\V2\DynamicRepresentative;
use App\V2\RepresentativePosition;
use App\Http\Traits\FormTrait;
use App\Http\Traits\FileTrait;

class RepresentativePositionController extends Controller
{
        use FormTrait;
        use FileTrait;
    
        public function index(Request $request)
        {
            if($request->ajax()) {
    
                $data = RepresentativePosition::all();
    
                return DataTables::of($data)
    
                    ->addColumn('name', function($row){
                        return $row->name;
                    })
    
            
                    ->addColumn('id', function($row){
                        return $row->id;
                    })
    
          
    
                    ->addColumn('action', function($row){
                        return "<a class='edit-link' href='" . route('admin.representative-positions.edit', $row->id) . "'>".
                            '<i class="fa fa-edit" aria-hidden="true"></i></a>'.
                            "<a data-toggle='modal' class='delete-link' href='#deleteModal' id='" .route('admin.representative-positions.destroy', $row->id) . "'>".
                            "<i class='fa fa-trash' style='color: red;' aria-hidden='true'></i>";
                    })
    
                    ->make(true);
            }
    
    
            return view('components.table_ajax')->with([
                'layout'    => 'layouts.cms',
                'pageTitle'	=> 'Representatives Positions',
                'table_title' => '',
                'slug'		=> 'representative',
                'custom_btn' => "<a href='" . route('admin.representative-positions.create') ."' class='btn btn-primary'>Add Representative Position</a>",
                'headers'	=> ['id', 'Name','Action'],
                'action' => route('admin.representative-positions.index'),
                'columns' => json_encode([
                    ['data' => 'id', 'name' => 'id'],
                    ['data' =>  'name', 'name'=> 'name'],
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
                'form_action'	=> route('admin.representative-positions.store'),
    
                'boxes' => [
                    [
                        'wrapper-class' => 'col-md-12',
                        'class' => 'box-default',
                        'box-header' => 'Info',
                        'form_fields' => [
                            $this->drawHtml('small_text', 'Position Name', 'name', $request->old('name') , null, '', 'col-md-6 required'),
                        ],
                    ],
                ]
            ]);
        }
    
        public function store(Request $request)
        {
            
            $this->validate($request, [
                'name' => 'required',
            ]);
    
            $position = new RepresentativePosition();
            
            $position->name = request('name');
            
    
            $position->save();
            
            return redirect()->route('admin.representative-positions.index')->with('message', 'Representative Added Successfully');
        }
        
        public function edit($id)
        {
            $position = RepresentativePosition::find($id);
            
            return view('components.form')->with([
                'layout'        => 'layouts.cms',
                'pageTitle'		=> 'Edit Representative Position',
                'method'		=> 'update',
                'form_action'	=> route('admin.representative-positions.update', $id),
                
                'boxes' => [
                    [
                        'wrapper-class' => 'col-md-12',
                        'class' => 'box-default',
                        'box-header' => 'Info',
                        'form_fields' => [
                            $this->drawHtml('small_text', 'Position Name', 'name', $position->name , null, '', 'col-md-6 required'),
                        ],
                    ],
                    ]
                ]);
            }
            
            public function update($id, Request $request)
            {
                $this->validate($request, [
                    'name' => 'required'
            ]);
    
            $position = RepresentativePosition::find($id);
    
            $position->name = request('name');
    
            $position->save();
    
            return redirect()->route('admin.representative-positions.index')->with('message', 'Representative Updated Successfully');
        }
    
        public function destroy($id)
        {
            $r = RepresentativePosition::find($id);
            
            $r->delete();
    
            return back()->with('message', 'representative-positions Deleted successfully');
        }
    
    

}
