<?php

namespace App\Http\Controllers\Admin;

use App\V2\AnswerCouncilNationalPoll;
use App\V2\AnswerCouncilNationalPollQuestion;
use App\V2\CouncilNationalPollQuestion;
use App\V2\CouncilNationalPoll;
use App\Http\Traits\FileTrait;
use App\Http\Traits\FormTrait;
use App\V2\CouncilNationalPollVote;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use DataTables;
class NationalCouncilPollQuestionsController extends Controller
{
    use FormTrait;
    use FileTrait;
    /**
     * Display a listing of the resource.
     *
     * @param  \App\CouncilNationalPoll  $councilNationalPoll
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request,CouncilNationalPoll $national_council_poll)
    {
        if($request->ajax()) {
            $data = $national_council_poll->questions;

            return DataTables::of($data)
                ->addColumn('question', function ($row) {
                    return $row->question;
                })

         /*       // admin/national-council-poll/{national_council_poll}/questions/{question}/answers               |
                ->addColumn('answers', function ($row) use ($national_council_poll) {
                    return "<a href='" . route('admin.national-council-poll.questions.answers.index', ['national_council_poll'=> $national_council_poll, 'question'=> $row]) . "' class='text-success'>Answers</a>";
                })*/

                ->addColumn('action', function ($row) use($national_council_poll) {
                    return "<a class='edit-link' href='" . route('admin.national-council-poll.questions.edit', ['national_council_poll'=>$national_council_poll,'question'=>$row]) . "'>" .
                        '<i class="fa fa-edit" aria-hidden="true"></i></a>' .
                        "<a data-toggle='modal' class='delete-link' href='#deleteModal' id='" . route('admin.national-council-poll.questions.destroy',['national_council_poll'=>$national_council_poll,'question'=>$row] ) . "'>" .
                        "<i class='fa fa-trash' style='color: red;' aria-hidden='true'></i>";
                })
                ->rawColumns(['action', 'answers'])
                ->make(true);
        }

        return view('components.table_ajax')->with([
            'layout'    => 'layouts.cms',
            'pageTitle'	=> 'أسئلة إستبيان مجلس الوطني',
            'table_title' => '',
            'slug'		=> 'national-council-poll',
            'custom_btn' => "<a href='" . route('admin.national-council-poll.questions.create',['national_council_poll'=>$national_council_poll]) ."' class='btn btn-primary'>Add National Council Poll</a>",
            'headers'	=> ['id', 'َQuestion', /*'Answers',*/'Action'],
            'action' => route('admin.national-council-poll.index'),
            'columns' => json_encode([
                ['data' => 'id', 'name' => 'id'],
                ['data' => 'question', 'name' => 'question'],
/*                ['data' => 'answers', 'name' => 'answers'],*/
                ['data' =>  'action', 'name'=> 'action', 'searchable' => false, 'sortable' => false],
            ]),
        ]);

    }


    public function create(Request $request,CouncilNationalPoll $national_council_poll)
    {

        return view('components.form')->with([
            'layout'         => 'layouts.cms',
            'pageTitle'		=> 'Add Question',
            'method'		=> 'post',
            'form_action'	=> route('admin.national-council-poll.questions.store',$national_council_poll),

            'boxes' => [
                [
                    'wrapper-class' => 'col-md-6 clear-both',
                    'class' => 'box-default',
                    'box-header' => 'Options',
                    'form_fields' => [
                        $this->drawHtml('small_text', 'Question', 'question',  null, null,'Question', 'col-md-12 required')
                    ],
                ],
                [
                    'wrapper-class' => 'col-md-6 clear-both',
                    'class' => 'box-default',
                    'box-header' => 'Options',
                    'form_fields' => [
                        $this->drawHtml('multi_option', 'Options', 'option',  null, null,'Option', 'col-md-12 required',true),

                    ],
                ]
            ]
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\CouncilNationalPoll  $councilNationalPoll
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request, CouncilNationalPoll $national_council_poll)
    {

        $this->validate($request,[
            'question'=>'required',
            'options.*.name_ar' => 'required'
            ]);

        $question = $national_council_poll->questions()->create([
            'question' => $request->question
        ]);

        foreach ($request->options as $option){
            $question->answers()->create([
                'answer' => $option['name_ar']
            ]);
        }

        return redirect()->route('admin.national-council-poll.questions.index',$national_council_poll)->with('message', 'Poll Created Successfully');
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\CouncilNationalPoll  $councilNationalPoll
     * @param  \App\CouncilNationalPollQuestion  $councilNationalPollQuestion
     * @return \Illuminate\Http\Response
     */
    public function show(CouncilNationalPoll $councilNationalPoll, CouncilNationalPollQuestion $councilNationalPollQuestion)
    {
        //
    }


    public function edit(CouncilNationalPoll $national_council_poll, CouncilNationalPollQuestion $question)
    {
        $default_options = [];

        foreach($question->answers as $option){
            $default_options[] = [
                'name_ar' => $option->answer,
                'id' => $option->id,
            ];
        }

        return view('components.form')->with([
            'layout'         => 'layouts.cms',
            'pageTitle'		=> 'Edit Poll',
            'method'		=> 'update',
            'form_action'	=>  route('admin.national-council-poll.questions.update',['national_council_poll'=>$national_council_poll,'question'=>$question]),

            'boxes' => [
                [
                    'wrapper-class' => 'col-md-6',
                    'class' => 'box-default',
                    'box-header' => 'Content',
                    'form_fields' => [
                        $this->drawHtml('small_text', 'Question(Arabic)', 'question', $question->question , null, '', 'col-md-12 right-to-left required'),
                      ],
                ],

                [
                    'wrapper-class' => 'col-md-6 clear-both',
                    'class' => 'box-default',
                    'box-header' => 'Options',
                    'form_fields' => [
                        $this->drawHtml('multi_option', 'Options', 'option',  $default_options, null,'Option', 'col-md-12',true),
                    ]
                ],
            ]
        ]);
    }


    public function update(CouncilNationalPoll $national_council_poll, CouncilNationalPollQuestion $question,Request $request)
    {
        $this->validate($request,[
            'question'=>'required',
            'options.*.name_ar' => 'required'
        ]);


        $options = request('options');
        $options = collect($options);

        $options = array_filter($options->pluck('id')->toArray());

        //Delete the answers deleted by the user
        $question->answers->each(function($answer) use ($options) {
            if(!in_array($answer->id, $options))
                $answer->delete();
        });

        foreach(request('options') as $option) {
            if(!$option['name_ar'])
                continue;
            if(isset($option['id'])){
                $answer = AnswerCouncilNationalPoll::find($option['id']);
                $answer->answer = $option['name_ar'];
                $answer->save();
            }else{
                AnswerCouncilNationalPoll::create([
                    'question_id'=>$question->id,
                    'answer' => $option['name_ar']
                ]);
            }
        }


        return redirect()->route('admin.national-council-poll.questions.index',$national_council_poll)->with('message', 'Poll Updated Successfully');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\CouncilNationalPoll  $councilNationalPoll
     * @param  \App\CouncilNationalPollQuestion  $councilNationalPollQuestion
     * @return \Illuminate\Http\Response
     */
    public function destroy(CouncilNationalPoll $national_council_poll, CouncilNationalPollQuestion $question)
    {
        $question->delete();
        return back()->with('message', 'Question Deleted Successfully');
    }
}
