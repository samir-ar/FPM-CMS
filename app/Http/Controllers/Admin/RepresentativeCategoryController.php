<?php


namespace App\Http\Controllers\Admin;

use DataTables;
use App\Http\Controllers\Controller;
use App\V2\DynamicRepresentative;
use App\Http\Traits\FormTrait;
use App\Http\Traits\FileTrait;
use Illuminate\Http\Request;


class RepresentativeCategoryController extends Controller
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

            $data = DynamicRepresentative::query();

            return DataTables::of($data)
                ->addColumn('title_ar', function ($row) {
                    return $row->getTranslation('title', 'ar');
                })

                ->addColumn('text_ar', function ($row) {
                    // $text = $row->getTranslation('text', 'ar');
                    // return (strlen($text) < 41) ? $text : substr($text, 0, 40) . "...";
                    return $row->getTranslation('text', 'ar');
                })

                ->addColumn('action', function ($row) {
                    return "<a class='edit-link' href='" . route('admin.representatives.category.edit', $row->id) . "'>" .
                        '<i class="fa fa-edit" aria-hidden="true"></i></a>' .
                        "<a data-toggle='modal' class='delete-link' href='#deleteModal' id='" . route('admin.representatives.category.destroy', $row->id) . "'>" .
                        "<i class='fa fa-trash' style='color: red;' aria-hidden='true'></i>";
                })

                ->escapeColumns('text')
                ->make(true);
        }


        return view('components.table_ajax')->with([
            'layout'    => 'layouts.cms',
            'pageTitle'    => 'Representatives',
            'table_title' => '',
            'slug'        => 'representative',
            'custom_btn' => "<a href='" . route('admin.representatives.category.create') . "' class='btn btn-primary'>Add Category</a>",
            'headers'    => ['id', 'Title', 'Text', 'Action'],
            'action' => route('admin.representatives.index'),
            'columns' => json_encode([
                ['data' => 'id', 'name' => 'id'],
                ['data' =>  'title_ar', 'name' => 'title'],
                ['data' =>  'text_ar', 'name' => 'text'],
                ['data' => 'action', 'name' => 'action', 'searchable' => false, 'sortable' => false],
            ]),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
        return view('components.form')->with([
            'layout'         => 'layouts.cms',
            'pageTitle'        => 'Add Representative Category',
            'method'        => 'post',
            'form_action'    => route('admin.representatives.category.store'),

            'boxes' => [
                [
                    'wrapper-class' => 'col-md-12',
                    'class' => 'box-default',
                    'box-header' => 'Info',
                    'form_fields' => [
                        $this->drawHtml('small_text', 'Title(English)', 'title', $request->old('name'), null, '', 'col-md-6 required'),
                        $this->drawHtml('small_text', 'Title(Arabic)', 'title_ar', $request->old('name_ar'), null, '', 'col-md-6 right-to-left required'),

                        $this->drawHtml('text', 'Text(English)', 'text', $request->old('category'), null, '', 'col-md-6 required'),
                        $this->drawHtml('text', 'Title(Arabic)', 'text_ar', $request->old('category_ar'), null, '', 'col-md-6 right-to-left required'),

                        $this->drawHtml('number', 'Order  (default is 0)', 'order', $request->old('order'), null, '', 'col-md-12'),
                    ],
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
            'text' => 'required',
            'text_ar' => 'required',
            'title_ar' => 'required',
            'title' => 'required'
        ]);

        $representatives = new DynamicRepresentative();

        $representatives->setTranslations('title', [
            'en' => request('title'),
            'ar' => request('title_ar'),
        ]);

        $representatives->setTranslations('text', [
            'en' => request('text'),
            'ar' => request('text_ar'),
        ]);

        if (request('order')) {
            $representatives->order = request('order');
        } else {
            $representatives->order = 0;
        }


        $representatives->save();

        return redirect()->route('admin.representatives.category.index')->with('message', 'Category Added Successfully');
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

    /**t
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $r = DynamicRepresentative::find($id);

        return view('components.form')->with([
            'layout'        => 'layouts.cms',
            'pageTitle'        => 'Edit Category',
            'method'        => 'update',
            'form_action'    => route('admin.representatives.category.update', $id),

            'boxes' => [
                [
                    'wrapper-class' => 'col-md-12',
                    'class' => 'box-default',
                    'box-header' => 'Info',
                    'form_fields' => [
                        $this->drawHtml('small_text', 'Title(English)', 'title', $r->getTranslation('title', 'en'), null, '', 'col-md-6 required'),
                        $this->drawHtml('small_text', 'Title(Arabic)', 'title_ar', $r->getTranslation('title', 'ar'), null, '', 'col-md-6 right-to-left required'),

                        $this->drawHtml('small_text', 'Text(English)', 'text', $r->getTranslation('text', 'en'), null, '', 'col-md-6 required'),
                        $this->drawHtml('small_text', 'Text(Arabic)', 'text_ar', $r->getTranslation('text', 'ar'), null, '', 'col-md-6 right-to-left required'),

                        $this->drawHtml('number', 'Order', 'order', $r->order, null, '', 'col-md-12'),

                    ],
                ],
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
            'title' => 'required',
            'title_ar' => 'required',
            'text' => 'required',
            'text_ar' => 'required',
        ]);

        $category = DynamicRepresentative::find($id);

        $category->setTranslations('title', [
            'en' => request('title'),
            'ar' => request('title_ar'),
        ]);

        $category->setTranslations('text', [
            'en' => request('text'),
            'ar' => request('text_ar'),
        ]);

        if (request('order')) {
            $category->order = request('order');
        }

        $category->save();
        return redirect()->route('admin.representatives.category.index')->with('message', 'Category has heen updated successfully');
    }


    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $representatives = DynamicRepresentative::find($id);

        $persons = $representatives->persons->each(function ($p) {
            $this->removeFile($p->image);
        });

        $representatives->delete();
        return redirect()->route('admin.representatives.category.index')->with('message', 'Category has heen deleted successfully');
    }
}
