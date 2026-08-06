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
            'custom_btn' =>
                "<a href='" . route('admin.representatives.create') ."' class='btn btn-primary' style='margin-right:6px'>Add Representative</a>" .
                "<a href='" . route('admin.representatives.normalize-order') ."' class='btn btn-default' title='يصلح أي فجوات أو تكرار في الترتيب داخل كل فئة'><i class='fa fa-sort-numeric-asc'></i> إعادة ترقيم الترتيب</a>",
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

                        $this->drawHtml('number', 'Order', 'order', $request->old('order') , null, 'اتركه فارغاً للإضافة في نهاية القائمة، أو أدخل رقم الموقع الذي تريده — سيتم تلقائياً إزاحة البقية', 'col-md-12'),
                        // $this->drawHtml('small_text', 'Type', 'type', $request->old('type') , null, 'keep it empty', 'col-md-12'),
                        $this->drawHtml('select-box', 'Type', 'type', $request->old('type'), [NULL => 'Select Status','Founder' => 'Founder','President' => 'President','Representative' => 'Representative'], '', 'col-md-12'),


                    ],
                ],
            ]
        ]);
    }

    /**
     * Make room for a person being inserted at $order within $categoryId —
     * shifts everyone already at or past that position one step back.
     */
    private function shiftOrderForInsert($categoryId, $order)
    {
        Person::where('dynamic_representative_id', $categoryId)
            ->where('order', '>=', $order)
            ->increment('order');
    }

    /**
     * Close the gap left behind after a person at $order leaves $categoryId
     * (deleted, or moved to a different category).
     */
    private function shiftOrderForRemoval($categoryId, $order)
    {
        Person::where('dynamic_representative_id', $categoryId)
            ->where('order', '>', $order)
            ->decrement('order');
    }

    /**
     * Move a person from $oldOrder to $newOrder within the same category,
     * shifting everyone in between by one step to keep the sequence gapless.
     */
    private function moveOrder($categoryId, $personId, $oldOrder, $newOrder)
    {
        if ($newOrder == $oldOrder) {
            return;
        }

        if ($newOrder > $oldOrder) {
            Person::where('dynamic_representative_id', $categoryId)
                ->where('id', '!=', $personId)
                ->whereBetween('order', [$oldOrder + 1, $newOrder])
                ->decrement('order');
        } else {
            Person::where('dynamic_representative_id', $categoryId)
                ->where('id', '!=', $personId)
                ->whereBetween('order', [$newOrder, $oldOrder - 1])
                ->increment('order');
        }
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

        $categoryId = request('category');
        $person->dynamic_representative_id = $categoryId;

        $person->image = $this->moveFile(request('image'), 'images/representatives');

        $maxOrder = Person::where('dynamic_representative_id', $categoryId)->max('order') ?? 0;

        if (request('order') !== null && request('order') !== '') {
            $order = max(1, min((int) request('order'), $maxOrder + 1));
            $this->shiftOrderForInsert($categoryId, $order);
        } else {
            $order = $maxOrder + 1;
        }
        $person->order = $order;
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

                        $this->drawHtml('image', 'Image', 'image', $person->image ? 'images/representatives/' . $person->image : null, null, '', 'col-md-12 '),

                        $this->drawHtml('number', 'Order', 'order', $person->order , null, 'غيّر هذا الرقم لنقل الشخص إلى موقع آخر — سيتم تلقائياً إزاحة البقية للحفاظ على ترتيب متسلسل', 'col-md-12'),
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

        $oldCategoryId = $person->dynamic_representative_id;
        $oldOrder = $person->order;

        $person->setTranslations('name', [
            'en' => request('name'),
            'ar' => request('name_ar'),
        ]);

        $newCategoryId = request('category');
        $person->dynamic_representative_id = $newCategoryId;

        if(request('image')){
            $this->removeFile($person->image);
            $person->image = $this->moveFile(request('image'), 'images/representatives');
        }
        if(request('position_id')){
            $person->representative_position_id = request('position_id');
        }

        $orderInput = request('order');
        $categoryChanged = $newCategoryId != $oldCategoryId;

        if ($categoryChanged) {
            // Leaving the old category: close the gap left behind there.
            $this->shiftOrderForRemoval($oldCategoryId, $oldOrder);

            $maxOrderNew = Person::where('dynamic_representative_id', $newCategoryId)->max('order') ?? 0;
            if ($orderInput !== null && $orderInput !== '') {
                $newOrder = max(1, min((int) $orderInput, $maxOrderNew + 1));
                $this->shiftOrderForInsert($newCategoryId, $newOrder);
            } else {
                $newOrder = $maxOrderNew + 1;
            }
            $person->order = $newOrder;
        } elseif ($orderInput !== null && $orderInput !== '') {
            $maxOrder = Person::where('dynamic_representative_id', $oldCategoryId)->max('order') ?? 1;
            $newOrder = max(1, min((int) $orderInput, $maxOrder));
            $this->moveOrder($oldCategoryId, $person->id, $oldOrder, $newOrder);
            $person->order = $newOrder;
        }

        $person->save();

        return redirect()->route('admin.representatives.index')->with('message', 'Representative Updated Successfully');
    }

    public function destroy($id)
    {
        $r = Person::find($id);

        $categoryId = $r->dynamic_representative_id;
        $order = $r->order;

        $this->removeFile($r->image);

        $r->delete();

        $this->shiftOrderForRemoval($categoryId, $order);

        return back()->with('message', 'Representatives Deleted successfully');
    }

    /**
     * Safety-net button: re-numbers every category's people to a clean 1..N
     * sequence (ties/gaps broken by current order, then id) — a manual reset
     * on top of the automatic shifting store()/update()/destroy() already do.
     */
    public function normalizeOrder()
    {
        DynamicRepresentative::all()->each(function ($category) {
            $people = Person::where('dynamic_representative_id', $category->id)
                ->orderBy('order')
                ->orderBy('id')
                ->get();

            $order = 1;
            foreach ($people as $person) {
                if ($person->order != $order) {
                    $person->order = $order;
                    $person->save();
                }
                $order++;
            }
        });

        return back()->with('message', 'تمت إعادة ترقيم الترتيب لجميع الفئات بنجاح');
    }
}
