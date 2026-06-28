<?php

namespace App\Http\Controllers\Admin;


use App\Group;
use DB;

use Carbon\Carbon;
use App\Poll;
use DataTables;
use App\PollOption;
use App\Events\NewItem;
use Illuminate\Http\Request;
use App\Http\Traits\FormTrait;
use App\Http\Traits\FileTrait;
use App\Http\Controllers\Controller;

class PollsController extends Controller
{
    use FormTrait;
    use FileTrait;

    public function index(Request $request)
    {
        if($request->ajax()) {


            $data = Poll::latest();


            return DataTables::of($data)

                ->addColumn('my_image', function($row){
                    return $this->drawImage($row->image);
                })

                ->addColumn('my_question', function($row){
                    return $row->getTranslation('question', 'en');
                })

                ->addColumn('my_details', function($row){
                    return $row->getTranslation('details', 'en');
                })

                ->addColumn('answers', function($row){
                    return "<a href='" . route('admin.userPolls.index').'?poll_id='. $row->id ."'>Answers</a>";
                })

                ->addColumn('action', function($row){
                    return "<a class='edit-link' href='" . route('admin.polls.edit', $row->id) . "'>".
                        '<i class="fa fa-edit" aria-hidden="true"></i></a>'.
                        "<a data-toggle='modal' class='delete-link' href='#deleteModal' id='" .route('admin.polls.destroy', $row->id) . "'>".
                        "<i class='fa fa-trash' style='color: red;' aria-hidden='true'></i>";
                })
                ->rawColumns(['id', 'question', 'details', 'created_at', 'action', 'answers','my_question', 'my_details'])
                ->make(true);
        }


        return view('components.table_ajax')->with([
            'layout'    => 'layouts.cms',
            'pageTitle'	=> 'Polls',
            'table_title' => '',
            'slug'		=> 'Poll',
            'custom_btn' => "<a href='" . route('admin.polls.create') ."' class='btn btn-primary'>Add Polls</a>",
            'headers'	=> ['id', 'Question',   'Archive Date', 'Answers', 'Action'],
            'action' => route('admin.polls.index'),
            'columns' => json_encode([
                ['data' => 'id', 'name' => 'id'],
                ['data' =>  'my_question', 'name'=> 'question'],
                //['data' =>  'my_details', 'name'=> 'my_details'],
                ['data' =>  'created_at', 'name'=> 'created_at'],
                ['data' =>  'answers', 'name'=> 'answers'],
                ['data' => 'action', 'name' => 'action', 'searchable' => false, 'sortable' => false],
            ]),

        ]);
    }

    public function create(Request $request)
    {

        $groups = Group::all()->pluck('name', 'group_id')->toArray();

        return view('components.form')->with([
            'layout'         => 'layouts.cms',
            'pageTitle'		=> 'Create Poll',
            'method'		=> 'post',
            'form_action'	=> route('admin.polls.store'),

            'boxes' => [
                [
                    'wrapper-class' => 'col-md-6',
                    'class' => 'box-default',
                    'box-header' => 'Content',
                    'form_fields' => [
                        $this->drawHtml('select-box', 'Force Language', 'strict_lang', null, $this->strictLang(), '', 'col-md-12 required'),

                        $this->drawHtml('small_text', 'Question', 'question', $request->old('question') , null, '', 'col-md-12 required'),
                        $this->drawHtml('small_text', 'Question(Arabic)', 'question_ar', $request->old('question_ar') , null, '', 'col-md-12 right-to-left required'),
                        $this->drawHtml('date-time-picker', 'Expiry Date', 'expiry_date', "", null, '', 'col-md-12 required'),

                        /*
                        $this->drawHtml('text', 'Details', 'details', $request->old('details'), null, '', 'col-md-12 no-ck required'),
                        $this->drawHtml('text', 'Details(Arabic)', 'details_ar', $request->old('details_ar'), null, '', 'col-md-12 no-ck right-to-left required'),
                        */


                        $this->drawHtml('checkbox', 'Show', 'show', true, null, '', 'col-md-12'),
                    ],
                ],

                [
                    'wrapper-class' => 'col-md-6 ',
                    'class' => 'box-primary',
                    'box-header' => 'Permissions',
                    'form_fields' => [
                        $this->drawHtml('multiple-select-box', 'Groups', 'groups[]', '', $groups, '', 'col-md-12 required'),

                        //$this->drawHtml('checkbox', 'All Groups', 'all_groups', null , null, '', 'col-md-12 '),

                        $this->drawHtml('checkbox', 'Send Push Notification', 'push_notification', $request->old('push_notification') , null, '', 'col-md-12 '),


                    ]
                ],

                    [
                        'wrapper-class' => 'col-md-6 clear-both',
                        'class' => 'box-default',
                        'box-header' => 'Options',
                        'form_fields' => [
                            $this->drawHtml('multi_option', 'Options', 'option',  null, null,'Option', 'col-md-12 required'),

                        ],
                    ]
                    ,[
                        'wrapper-class' => 'col-md-6',
                        'class' => 'box-primary',
                        'box-header' => 'Push Notification',
                        'form_fields' => [
                            $this->drawHtml('small_text', 'Name', 'name_notification', $request->old('name') , null, '', 'col-md-12 '),
                            $this->drawHtml('text', 'Details', 'details_notification', $request->old('details'), null, '', 'col-md-12'),
                        ]
                    ]
            ]
        ]);
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'expiry_date' => 'required|date',
            'question' => 'required',
            //'details' => 'required',
            'groups' => 'required_without:all_groups',
            'options' => 'required|array',
            'details_notification' => 'required_with:name_notification'
        ]);

        if(sizeof(request('options')) < 2){
            return back()->withErrors(['You must enter at least two options']);
        }


        DB::transaction(function () use ($request){
            $poll = new Poll();

            $poll->setTranslations('question', [
                'en' => request('question'),
                'ar' => request('question_ar'),
            ]);

            /*
            $poll->setTranslations('details', [
                'en' => request('details'),
                'ar' => request('details_ar'),
            ]);
            */
            $poll->strict_lang = request('strict_lang');

            $poll->show = request('show') ? true : false;
            $poll->expiry_date = Carbon::parse(request('expiry_date'))->toDateTimeString();

            $poll->save();


            foreach(request('options') as $o) {
                $option = new PollOption;

                $option->setTranslations('option', [
                    'en' => $o['name'],
                    'ar' => isset($o['name_ar']) ? $o['name_ar'] : '',
                ]);

                $option->poll_id = $poll->id;
                $option->save();
            }


            if(request('all_groups')){
                $groups = Group::all()->pluck('group_id')->toArray();
                $request->merge(['groups' => $groups]);
            }


            $poll->groups()->sync(request('groups'));


            if(request('push_notification')){

                if(request('name_notification')){
                    $request->request->add([
                        'title' => request('name_notification'),
                        'title_ar' => request('name_notification'),
                        'text' => request('details_notification'),
                        'text_ar' => request('details_notification')
                    ]);
                }else{
                    $request->request->add([
                        'title' => request('question'),
                        'title_ar' => request('question_ar'),
                        'text' => request('question'),
                        'text_ar' => request('question_ar')
                    ]);
                }

            //add the events to the request for the event
            $request->request->add(['poll' => $poll->id]);

                event(new NewItem($request));
            }
        });

        return redirect()->route('admin.polls.index')->with('message', 'Poll Created Successfully');
    }

    public function edit($id)
    {
        $groups = Group::all()->pluck('name', 'group_id')->toArray();
        $poll = Poll::find($id);
        $default_groups = !$poll->groups()->get()->isempty() ? $poll->groups()->get()->pluck('group_id')->toArray() : '';

        $default_options = [];

        foreach($poll->options as $option){
            $default_options[] = [
                'name' => $option->getTranslation('option', 'en'),
                'name_ar' => $option->getTranslation('option', 'ar'),
                'id' => $option->id,
            ];
        }

        return view('components.form')->with([
            'layout'         => 'layouts.cms',
            'pageTitle'		=> 'Edit Poll',
            'method'		=> 'update',
            'form_action'	=> route('admin.polls.update', $id),

            'boxes' => [
                [
                    'wrapper-class' => 'col-md-6',
                    'class' => 'box-default',
                    'box-header' => 'Content',
                    'form_fields' => [
                        $this->drawHtml('select-box', 'Force Language', 'strict_lang', null, $this->strictLang(), '', 'col-md-12 required'),

                        $this->drawHtml('small_text', 'Question', 'question', $poll->getTranslation('question', 'en') , null, '', 'col-md-12 required'),
                        $this->drawHtml('small_text', 'Question(Arabic)', 'question_ar', $poll->getTranslation('question', 'ar') , null, '', 'col-md-12 right-to-left required'),

                        /*
                        $this->drawHtml('text', 'Details', 'details', $poll->getTranslation('details', 'en'), null, '', 'col-md-12 no-ck required'),
                        $this->drawHtml('text', 'Details(Arabic)', 'details_ar', $poll->getTranslation('details', 'ar'), null, '', 'col-md-12 no-ck right-to-left required'),
                        */
                        $this->drawHtml('date-time-picker', 'Expiry Date', 'expiry_date', $poll->expiry_date, null, '', 'col-md-12 required'),
                        $this->drawHtml('checkbox', 'Show', 'show', $poll->show, null, '', 'col-md-12'),
                    ],
                ],

                [
                    'wrapper-class' => 'col-md-6 ',
                    'class' => 'box-primary',
                    'box-header' => 'Permissions',
                    'form_fields' => [
                        $this->drawHtml('multiple-select-box', 'Groups', 'groups[]', $default_groups, $groups, '', 'col-md-12 required'),
                        //$this->drawHtml('checkbox', 'All Groups', 'all_groups', null , null, '', 'col-md-12 '),
                    ]
                ],

                [
                    'wrapper-class' => 'col-md-6 clear-both',
                    'class' => 'box-default',
                    'box-header' => 'Options',
                    'form_fields' => [
                        $this->drawHtml('multi_option', 'Options', 'option',  $default_options, null,'Option', 'col-md-12'),

                    ]
                ],
            ]
        ]);
    }

    public function update($id, Request $request)
    {
        $this->validate($request, [
            'question' => 'required',
            'expiry_date'=> 'required|date',
            //'details' => 'required',
            'groups' => 'required_without:all_groups',
        ]);

        DB::transaction(function () use ($id, $request){
            $poll = Poll::find($id);

            $poll->setTranslations('question', [
                'en' => request('question'),
                'ar' => request('question_ar'),
            ]);

            /*
            $poll->setTranslations('details', [
                'en' => request('details'),
                'ar' => request('details_ar'),
            ]);
            */

            $poll->show = request('show') ? true : false;
            $poll->expiry_date = Carbon::parse(request('expiry_date'))->toDateTimeString();
            $poll->strict_lang = request('strict_lang');

            $poll->save();

            //remove options
            $options = request('options');
            $options = collect($options);

            $options = array_filter($options->pluck('id')->toArray());

            $poll->options->each(function($o) use ($options){
               if(!in_array($o->id, $options))
                   $o->delete();
            });

            foreach(request('options') as $o) {

                if(!$o['name'])
                    continue;

                if(isset($o['id']))
                    $option = PollOption::find($o['id']);
                else{
                    $option = new PollOption();
                    $option->poll_id = $poll->id;
                }

                $option->setTranslations('option', [
                    'en' => $o['name'],
                    'ar' => isset($o['name_ar']) ? $o['name_ar'] : '',
                ]);

                $option->save();
            }

            if(request('all_groups')){
                $groups = Group::all()->pluck('group_id')->toArray();
                $request->merge(['groups' => $groups]);
            }

            $poll->groups()->sync(request('groups'));
        });

        return redirect()->route('admin.polls.index')->with('message', 'Poll Updated Successfully');
    }

    public function destroy($id)
    {
        Poll::find($id)->delete();
        return back()->with('message', 'Poll Deleted Successfully');
    }

    private function strictLang()
    {
        return [
            null => 'None',
            'ar' => 'Ar',
            'en' => 'En',
        ];
    }
}
