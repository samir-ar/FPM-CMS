<?php

namespace App\Http\Controllers\Admin;

use App\V2\AnswerCouncilNationalPoll;
use App\V2\AnswerCouncilNationalPollQuestion;
use App\V2\CouncilNationalPollQuestion;
use App\V2\CouncilNationalPoll;
use App\Http\Traits\FileTrait;
use App\Http\Traits\FormTrait;
use App\V2\CouncilNationalPollVote;
use App\Support\CouncilMemberLookup;
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
            'custom_btn' => "<a href='" . route('admin.national-council-poll.questions.create',['national_council_poll'=>$national_council_poll]) ."' class='btn btn-primary'>Add Question</a>",
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
            'scripts' => ['/js/council-poll-candidates.js'],   // loaded after jQuery (an inline script here would run before it)

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
                    'class' => 'box-primary',
                    'box-header' => 'Add a candidate from the member list',
                    'form_fields' => [$this->candidatePicker($national_council_poll->id)],
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
            'options.*.name_ar' => 'required',
            'options.*.member_id' => 'nullable|digits_between:1,20',
            ]);

        $question = $national_council_poll->questions()->create([
            'question' => $request->question
        ]);

        foreach ($request->options as $option){
            $question->answers()->create([
                'answer' => $option['name_ar'],
                'member_id' => !empty($option['member_id']) ? (string) $option['member_id'] : null,
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
            'scripts' => ['/js/council-poll-candidates.js'],

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
                    'class' => 'box-primary',
                    'box-header' => 'Add a candidate from the member list',
                    'form_fields' => [$this->candidatePicker($national_council_poll->id)],
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


    // Search the whole member list (name, phone or member ID) to pick a candidate, like the Internal Election.
    public function searchCandidates(Request $request, $id)
    {
        $q = trim((string) $request->get('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        return response()->json(array_map(
            fn($m) => $m + ['photo' => route('admin.national-council-poll.candidates.photo', [$id, $m['member_id']])],
            CouncilMemberLookup::search($q, null, 15)
        ));
    }

    // The candidate's picture for the picker's preview (a Representatives upload first, then the member list).
    public function candidatePhoto($id, $memberId)
    {
        return CouncilMemberLookup::photoResponse($memberId, false);
    }

    // The search box + results + "add as an option" for the Add / Edit Question pages.
    private function candidatePicker($pollId): string
    {
        $searchUrl = route('admin.national-council-poll.candidates.search', $pollId);

        return <<<HTML
        <div class="form-group col-md-12" id="candPicker" data-search-url="{$searchUrl}">
            <label>Search by Member ID, Mobile Number, or Name</label>
            <div style="display:flex; gap:8px;">
                <input type="text" id="candSearchInput" class="form-control" placeholder="Type then press Search..." autocomplete="off">
                <button type="button" id="candSearchBtn" class="btn btn-primary" style="white-space:nowrap;">Search</button>
            </div>
            <div id="candResults" style="border:1px solid #ddd; max-height:260px; overflow:auto; margin-top:5px; display:none;"></div>
            <p class="text-muted" style="margin-top:8px;">Picking a member adds him as an option below with his member ID. The + button under Options still adds a free-text option.</p>
        </div>
        HTML;
    }

    public function update(CouncilNationalPoll $national_council_poll, CouncilNationalPollQuestion $question,Request $request)
    {
        $this->validate($request,[
            'question'=>'required',
            'options.*.name_ar' => 'required',
            'options.*.member_id' => 'nullable|digits_between:1,20',
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
                    'answer' => $option['name_ar'],
                    'member_id' => !empty($option['member_id']) ? (string) $option['member_id'] : null,
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
