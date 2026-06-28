<?php

namespace App\Http\Controllers\Admin;

use App\V2\AnswerCouncilNationalPollQuestion;
use App\V2\CouncilNationalPoll;
use App\V2\CouncilNationalPollQuestion;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use DataTables;

class NationalCouncilPollQuestionAnswersController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @param  \App\CouncilNationalPollQuestion  $councilNationalPollQuestion
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request, CouncilNationalPoll $national_council_poll, CouncilNationalPollQuestion $question)
    {
        if($request->ajax()) {
            $data = $question->answers ;

        return DataTables::of($data)
            ->addColumn('answer', function ($row) {
                return $row->answer;
            })

            ->addColumn('action', function ($row) {
                return "<a class='edit-link' href='" . route('admin.national-council-poll.edit', $row->id) . "'>" .
                    '<i class="fa fa-edit" aria-hidden="true"></i></a>' .
                    "<a data-toggle='modal' class='delete-link' href='#deleteModal' id='" . route('admin.national-council-poll.destroy', $row->id) . "'>" .
                    "<i class='fa fa-trash' style='color: red;' aria-hidden='true'></i>";
            })

            ->rawColumns(['action'])

            ->make(true);
    }

        return view('components.table_ajax')->with([
            'layout'    => 'layouts.cms',
            'pageTitle'	=> ' أجوبة إستبيان مجلس الوطني',
            'table_title' => '',
            'slug'		=> 'national-council-poll',
            'custom_btn' => "<a href='" . route('admin.national-council-poll.questions.answers.create',['national_council_poll'=>$national_council_poll,'question'=>$question]) ."' class='btn btn-primary'>Add National Council Poll</a>",
            'headers'	=> ['id',  'Choices','Action'],
            'action' => route('admin.national-council-poll.index'),
            'columns' => json_encode([
                ['data' => 'id', 'name' => 'id'],
                ['data' => 'answer', 'name' => 'answer'],
                ['data' =>  'action', 'name'=> 'action', 'searchable' => false, 'sortable' => false],
            ]),
        ]);

    }

    /**
     * Show the form for creating a new resource.
     *
     * @param  \App\CouncilNationalPollQuestion  $councilNationalPollQuestion
     * @return \Illuminate\Http\Response
     */
    public function create(CouncilNationalPollQuestion $councilNationalPollQuestion)
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\CouncilNationalPollQuestion  $councilNationalPollQuestion
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request, CouncilNationalPollQuestion $councilNationalPollQuestion)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\CouncilNationalPollQuestion  $councilNationalPollQuestion
     * @param  \App\AnswerCouncilNationalPollQuestion  $answerCouncilNationalPollQuestion
     * @return \Illuminate\Http\Response
     */
    public function show(CouncilNationalPollQuestion $councilNationalPollQuestion, AnswerCouncilNationalPollQuestion $answerCouncilNationalPollQuestion)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\CouncilNationalPollQuestion  $councilNationalPollQuestion
     * @param  \App\AnswerCouncilNationalPollQuestion  $answerCouncilNationalPollQuestion
     * @return \Illuminate\Http\Response
     */
    public function edit(CouncilNationalPollQuestion $councilNationalPollQuestion, AnswerCouncilNationalPollQuestion $answerCouncilNationalPollQuestion)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\CouncilNationalPollQuestion  $councilNationalPollQuestion
     * @param  \App\AnswerCouncilNationalPollQuestion  $answerCouncilNationalPollQuestion
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, CouncilNationalPollQuestion $councilNationalPollQuestion, AnswerCouncilNationalPollQuestion $answerCouncilNationalPollQuestion)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\CouncilNationalPollQuestion  $councilNationalPollQuestion
     * @param  \App\AnswerCouncilNationalPollQuestion  $answerCouncilNationalPollQuestion
     * @return \Illuminate\Http\Response
     */
    public function destroy(CouncilNationalPollQuestion $councilNationalPollQuestion, AnswerCouncilNationalPollQuestion $answerCouncilNationalPollQuestion)
    {
        //
    }
}
