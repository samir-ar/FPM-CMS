<?php

namespace App\Http\Controllers\Admin;

use App\Faq;
use DataTables;
use App\FaqCategory;
use Illuminate\Http\Request;
use App\Http\Traits\FormTrait;
use App\Http\Controllers\Controller;

class FaqsController extends Controller
{
    use FormTrait;

    public function index(Request $request)
    {
        if($request->ajax()) {


            $data = Faq::leftJoin('faqs_categories', 'faqs_categories.id', '=', 'faqs.cat_id')
                ->select(['faqs.name', 'faqs.order', 'faqs.id', 'faqs_categories.name as cat_name', 'faqs.created_at']);

            return DataTables::of($data)

                ->addColumn('name_en', function($row){
                    return $row->getTranslation('name', 'en');
                })

                ->addColumn('category_en', function($row){
                    $category = json_decode($row->cat_name);
                    return $category->en;
                })

                ->addColumn('action', function($row){
                    return "<a class='edit-link' href='" . route('admin.faqs.edit', $row->id) . "'>".
                        '<i class="fa fa-edit" aria-hidden="true"></i></a>'.
                        "<a data-toggle='modal' class='delete-link' href='#deleteModal' id='" .route('admin.faqs.destroy', $row->id) . "'>".
                        "<i class='fa fa-trash' style='color: red;' aria-hidden='true'></i>";
                })
                
                ->rawColumns(['id', 'name_en', 'name_ar', 'category_en', 'order', 'action'])
                ->make(true);
        }


        return view('components.table_ajax')->with([
            'layout'    => 'layouts.cms',
            'pageTitle'	=> 'Faqs',
            'table_title' => '',
            'slug'		=> 'Faq',
            'custom_btn' => "<a href='" . route('admin.faqs.create') ."' class='btn btn-primary'>Add Faq</a>",
            'headers'	=> ['id', 'Name', 'Category', 'Order', 'Action'],
            'action' => route('admin.faqs.index'),
            'columns' => json_encode([
                ['data' => 'id', 'name' => 'id'],
                ['data' =>  'name_en', 'name'=> 'name'],
                ['data' =>  'category_en', 'name'=> 'faqs_categories.name'],
                ['data' => 'order', 'name' => 'order'],
                ['data' => 'action', 'name' => 'action', 'searchable' => false, 'sortable' => false],
            ]),

        ]);
    }

    public function create(Request $request)
    {
        $categories = FaqCategory::all()->pluck('name', 'id')->toArray();

        return view('components.form')->with([
            'layout'         => 'layouts.cms',
            'pageTitle'		=> 'Add Faq',
            'method'		=> 'post',
            'form_action'	=> route('admin.faqs.store'),

            'boxes' => [
                [
                    'wrapper-class' => 'col-md-6',
                    'class' => 'box-default',
                    'box-header' => '',
                    'form_fields' => [
                        $this->drawHtml('select-box', 'Category', 'cat', $request->old('cat'), $categories, '', 'col-md-12 required'),
                        $this->drawHtml('small_text', 'Name', 'name', $request->old('name') , null, '', 'col-md-12 required'),
                        $this->drawHtml('small_text', 'Name(Arabic)', 'name_ar', $request->old('name_ar') , null, '', 'col-md-12 right-to-left required'),

                        $this->drawHtml('text', 'Details', 'details', $request->old('details'), null, '', 'col-md-12 required'),
                        $this->drawHtml('text', 'Details(Arabic)', 'details_ar', $request->old('details_ar'), null, '', 'col-md-12 right-to-left required'),

                        $this->drawHtml('number', 'Order', 'order', $request->old('order') , null, '', 'col-md-2'),
                    ],
                ],

            ]
        ]);
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'name' => 'required',
            'details' => 'required',
            'cat'  => 'required|exists:faqs_categories,id'
        ]);

        $faq = new Faq();

        $faq->setTranslations('name', [
            'en' => request('name'),
            'ar' => request('name_ar'),
        ]);

        $faq->setTranslations('details', [
            'en' => request('details'),
            'ar' => request('details_ar'),
        ]);

        $faq->cat_id = request('cat');
        if(request('order'))
            $faq->order = request('order');

        $faq->save();

        return redirect()->route('admin.faqs.index')->with('message', 'Faq Created Successfully');
    }

    public function edit($id)
    {
        $faq = Faq::find($id);
        $categories = FaqCategory::all()->pluck('name', 'id')->toArray();

        return view('components.form')->with([
            'layout'         => 'layouts.cms',
            'pageTitle'		=> 'Edit Faq',
            'method'		=> 'update',
            'form_action'	=> route('admin.faqs.update', $id),

            'boxes' => [
                [
                    'wrapper-class' => 'col-md-6',
                    'class' => 'box-default',
                    'box-header' => '',
                    'form_fields' => [
                        $this->drawHtml('select-box', 'Category', 'cat', $faq->cat, $categories, '', 'col-md-12 required'),
                        $this->drawHtml('small_text', 'Name', 'name', $faq->getTranslation('name', 'en') , null, '', 'col-md-12 required'),
                        $this->drawHtml('small_text', 'Name(Arabic)', 'name_ar', $faq->getTranslation('name', 'ar')  , null, '', 'col-md-12 right-to-left required'),

                        $this->drawHtml('text', 'Details', 'details',  $faq->getTranslation('details', 'en'), null, '', 'col-md-12 required'),
                        $this->drawHtml('text', 'Details(Arabic)', 'details_ar',$faq->getTranslation('details', 'ar'), null, '', 'col-md-12 right-to-left required'),

                        $this->drawHtml('number', 'Order', 'order', $faq->order , null, '', 'col-md-2'),

                    ],
                ],
            ]
        ]);
    }

    public function update($id, Request $request)
    {
        $faq = Faq::find($id);

        $this->validate($request, [
            'name' => 'required',
            'details' => 'required',
            'cat'  => 'required|exists:faqs_categories,id'
        ]);

        $faq->setTranslations('name', [
            'en' => request('name'),
            'ar' => request('name_ar'),
        ]);

        $faq->setTranslations('details', [
            'en' => request('details'),
            'ar' => request('details_ar'),
        ]);

        $faq->cat_id = request('cat');
        if(request('order'))
            $faq->order = request('order');

        $faq->save();

        return redirect()->route('admin.faqs.index')->with('message', 'Faq Updated Successfully');
    }

    public function destroy($id)
    {
        Faq::find($id)->delete();

        return back()->with('message', 'Faq Deleted');
    }

}
