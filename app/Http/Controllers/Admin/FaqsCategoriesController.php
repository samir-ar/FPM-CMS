<?php

namespace App\Http\Controllers\Admin;

use DataTables;
use App\FaqCategory;
use Dotenv\Regex\Result;
use Illuminate\Http\Request;
use App\Http\Traits\FormTrait;
use App\Http\Controllers\Controller;

class FaqsCategoriesController extends Controller
{
    use FormTrait;

    public function index(Request $request)
    {
        if($request->ajax()) {

            $data = FaqCategory::query();
            
            return DataTables::of($data)
            ->addColumn('my_name', function($row){
                    return $row->getTranslation('name', 'en');
                })

                ->addColumn('my_name_ar', function($row){
                    return $row->getTranslation('name', 'ar');
                })

                ->addColumn('action', function($row){
                    return "<a class='edit-link' href='" . route('admin.faqsCategories.edit', $row->id) . "'>".
                        '<i class="fa fa-edit" aria-hidden="true"></i></a>'.
                        "<a data-toggle='modal' class='delete-link' href='#deleteModal' id='" .route('admin.faqsCategories.destroy', $row->id) . "'>".
                        "<i class='fa fa-trash' style='color: red;' aria-hidden='true'></i>";
                })
                ->rawColumns(['id', 'name', 'my_name_ar', 'order', 'action', 'my_name'])
                ->make(true);
        }


        return view('components.table_ajax')->with([
            'layout'    => 'layouts.cms',
            'pageTitle'	=> 'Faqs Categories',
            'table_title' => '',
            'slug'		=> 'Category',
            'custom_btn' => "<a href='" . route('admin.faqsCategories.create') ."' class='btn btn-primary'>Add Category</a>",
            'headers'	=> ['id', 'Name', 'Order', 'Action'],
            'action' => route('admin.faqsCategories.index'),
            'columns' => json_encode([
                ['data' => 'id', 'name' => 'id'],
                ['data' =>  'my_name', 'name'=> 'name'],
                ['data' => 'order', 'name' => 'order'],
                ['data' => 'action', 'name' => 'action', 'searchable' => false, 'sortable' => false],
            ]),

        ]);
    }

    public function create(Request $request)
    {
        return view('components.form')->with([
            'layout'         => 'layouts.cms',
            'pageTitle'		=> 'Add Category',
            'method'		=> 'post',
            'form_action'	=> route('admin.faqsCategories.store'),

            'boxes' => [
                [
                    'wrapper-class' => 'col-md-6',
                    'class' => 'box-default',
                    'box-header' => '',
                    'form_fields' => [
                        $this->drawHtml('small_text', 'Name', 'name', $request->old('name') , null, '', 'col-md-12 required'),
                        $this->drawHtml('small_text', 'Name(Arabic)', 'name_ar', $request->old('name_ar') , null, '', 'col-md-12 right-to-left required'),

                        $this->drawHtml('number', 'Order', 'order', $request->old('order') , null, '', 'col-md-12 '),
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

        $cat = new FaqCategory();

        $cat->setTranslations('name', [
            'en' => request('name'),
            'ar' => request('name_ar'),
        ]);

        if(request('order'))
            $cat->order = request('order');

        $cat->save();

        return redirect()->route('admin.faqsCategories.index')->with('message', 'Category Created');
    }

    public function edit($id)
    {

        $cat = FaqCategory::find($id);

        return view('components.form')->with([
            'layout'         => 'layouts.cms',
            'pageTitle'		=> 'Edit Category',
            'method'		=> 'update',
            'form_action'	=> route('admin.faqsCategories.update', $id),

            'boxes' => [
                [
                    'wrapper-class' => 'col-md-6',
                    'class' => 'box-default',
                    'box-header' => '',
                    'form_fields' => [
                        $this->drawHtml('small_text', 'Name', 'name', $cat->getTranslation('name', 'en') , null, '', 'col-md-12 required'),
                        $this->drawHtml('small_text', 'Name(Arabic)', 'name_ar', $cat->getTranslation('name', 'ar') , null, '', 'col-md-12 right-to-left required'),

                        $this->drawHtml('number', 'Order', 'order', $cat->order , null, '', 'col-md-12 '),
                    ],
                ],

            ]
        ]);
    }

    public function update($id, Request $request)
    {
        $cat = FaqCategory::find($id);

        $this->validate($request, [
            'name' => 'required',
        ]);

        $cat->setTranslations('name', [
            'en' => request('name'),
            'ar' => request('name_ar'),
        ]);

        if(request('order'))
            $cat->order = request('order');

        $cat->save();

        return redirect()->route('admin.faqsCategories.index')->with('message', 'Category Updated');
    }

    public function destroy($id)
    {
        FaqCategory::find($id)->delete();

        return back()->with('message', 'Category Deleted Successfully');
    }
}
