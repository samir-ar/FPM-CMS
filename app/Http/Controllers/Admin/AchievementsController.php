<?php

namespace App\Http\Controllers\Admin;

use DB;
use App\V2\Achievement;
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

class AchievementsController extends Controller
{
    use FormTrait;
    use FileTrait;


    public function index(Request $request)
    {
        if($request->ajax()) {


            $data = Achievement::latest();

            return DataTables::of($data)

                ->addColumn('title_en', function($row){
                    return $row->getTranslation('title', 'en');
                })

                ->addColumn('action', function($row){
                    return "<a class='edit-link' href='" . route('admin.achievements.edit', $row->id) . "'>".
                        '<i class="fa fa-edit" aria-hidden="true"></i></a>'.
                        "<a data-toggle='modal' class='delete-link' href='#deleteModal' id='" .route('admin.achievements.destroy', $row->id) . "'>".
                        "<i class='fa fa-trash' style='color: red;' aria-hidden='true'></i>";
                })
                ->rawColumns(['id', 'title_en', 'action'])
                ->make(true);
        }


        return view('components.table_ajax')->with([
            'layout'    => 'layouts.cms',
            'pageTitle'	=> 'achievements',
            'table_title' => '',
            'slug'		=> 'achievements',
            'custom_btn' => "<a href='" . route('admin.achievements.create') ."' class='btn btn-primary'>Add achievements</a>",
            'headers'	=> ['id', 'Title', 'Action'],
            'action' => route('admin.achievements.index'),
            'columns' => json_encode([
                ['data' => 'id', 'name' => 'id'],
                ['data' =>  'title_en', 'name'=> 'title_en'],
                ['data' => 'action', 'name' => 'action', 'searchable' => false, 'sortable' => false],
            ]),

        ]);
    }

    public function create(Request $request)
    {
        return view('components.form')->with([
            'layout'         => 'layouts.cms',
            'pageTitle'		=> 'Add achievements',
            'method'		=> 'post',
            'form_action'	=> route('admin.achievements.store'),
            'boxes' => [
                [
                    'wrapper-class' => 'col-md-6',
                    'class' => 'box-default',
                    'box-header' => 'Info',
                    'form_fields' => [
                        $this->drawHtml('small_text', 'Title (English)', 'title', $request->old('title') , null, '', 'col-md-12 '),
                        $this->drawHtml('small_text', 'Title (Arabic)', 'title_ar', $request->old('title_ar') , null, '', 'col-md-12 right-to-left'),
                        $this->drawHtml('text', 'Text (English)', 'text', $request->old('text'), null, '', 'col-md-12'),
                        $this->drawHtml('text', 'Text (Arabic)', 'text_ar', $request->old('text_ar'), null, '', 'col-md-12 right-to-left'),
                    ],
                ],
            ]
        ]);
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'title' => 'required',
            'text' => 'required',
        ]);
        $achievements = new Achievement();
        $achievements->setTranslations('title', [
            'en' => request('title'),
            'ar' => request('title_ar'),
        ]);
        $achievements->setTranslations('text', [
            'en' => request('text'),
            'ar' => request('text_ar'),
        ]);
        $achievements->save();

        //push notification
        if(request('push_notification')){
            $request->request->add([
                'title' => request('title'),
                'title_ar' => request('title_ar'),
                'text' => request('text'),
                'text_ar' => request('text_ar')
            ]);

            event(new NewItem($request));
        }

        return redirect()->route('admin.achievements.index')->with('message', 'achievements created successfully');
    }

    public function edit($id)
    {
        $achievements = Achievement::find($id);
        return view('components.form')->with([
            'layout'         => 'layouts.cms',
            'pageTitle'		=> 'Edit achievements',
            'method'		=> 'update',
            'form_action'	=> route('admin.achievements.update', $id),

            'boxes' => [
                [
                    'wrapper-class' => 'col-md-6',
                    'class' => 'box-default',
                    'box-header' => 'Info',
                    'form_fields' => [
                        $this->drawHtml('small_text', 'Title (English)', 'title', $achievements->getTranslation('title', 'en') , null, '', 'col-md-12 required'),
                        $this->drawHtml('small_text', 'Title (Arabic)', 'title_ar', $achievements->getTranslation('title', 'ar') , null, '', 'col-md-12 right-to-left required'),
                        $this->drawHtml('text', 'Text (English)', 'text', $achievements->getTranslation('text', 'en') , null, '', 'col-md-12 required'),
                        $this->drawHtml('text', 'Text (Arabic)', 'text_ar', $achievements->getTranslation('text', 'ar') , null, '', 'col-md-12 right-to-left required'),
                    ],
                ],
            ]
        ]);
    }

    public function update($id, Request $request)
    {

        $this->validate($request, [
            'title' => 'required',
        ]);
        $achievements = Achievement::find($id);
        $achievements->setTranslations('title', [
            'en' => request('title'),
            'ar' => request('title_ar'),
        ]);
        $achievements->setTranslations('text', [
            'en' => request('text'),
            'ar' => request('text_ar'),
        ]);

        $achievements->save();

        return redirect()->route('admin.achievements.index')->with('message', 'achievements Updated Successfully');
    }

    public function destroy($id)
    {
        $achievements = Achievement::find($id);
        $achievements->delete();

        return back()->with('message', 'achievements deleted successfully');
    }
}
