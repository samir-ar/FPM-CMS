<style>
    .delete-question{
        position: absolute;
        top: 33px;
        left: -7px;
        cursor: pointer;
    }

    .delete-answer{
        position: absolute;
        top: 7px;
        left: -24px;
        cursor: pointer;
    }

    .answer-element{
        position: relative;
    }

    .add-answer{
        float: right;
    }

    .question_wrapper{
        padding-bottom: 50px;
        display: table;
    }
</style>




@for($i=0; $i < count($default); $i++)
<div class="question_wrapper">
    <input type="hidden" name="questions[{{$i}}][id]" value="{{$default[$i]['id']}}" />
    <input type="hidden" id="question_index" value="{{$i}}" />
    <div class='form-group {{$class}}'>
        <div class="row">
            <div class="col-md-6">
                <label>Question</label>
                <input class='form-control' type='text' name='questions[{{$i}}][name]' value='{{$default[$i]['question']}}' placeholder='question' />
            </div>

            <div class="col-md-6 arabic-input">
                <label>Question (Arabic)</label>
                <input class='form-control' type='text' name='questions[{{$i}}][name_ar]' value='{{$default[$i]['question_ar']}}' placeholder='question' />
            </div>

        </div>
        <div class="delete-question delete-icon">
            <i class="fa fa-minus-circle" style="font-size:20px;color:red"></i>
        </div>
    </div>

    <div class="form-group answers {{$class}}" style="padding:0 50px;">
        <label>Answers</label>

        @for($j=0; $j < count($default[$i]['answers']); $j++)
            <div class="answer-element row">
                <input type="hidden" name='questions[{{$i}}][answers][{{$j}}][id]' value="{{$default[$i]['answers'][$j]['id']}}" />
                <div class="form-group col-md-6">
                    <input class='form-control' type='text' name='questions[{{$i}}][answers][{{$j}}][name]' value='{{$default[$i]['answers'][$j]['name']}}'
                           placeholder='Answer' style="margin-bottom:15px;"/>
                </div>

                <div class="form-group col-md-6">
                    <input class='form-control arabic-input' type='text' name='questions[{{$i}}][answers][{{$j}}][name_ar]' value='{{$default[$i]['answers'][$j]['name_ar']}}'
                           placeholder='Answer(Arabic)' style="margin-bottom:15px;"/>
                </div>
                <div class="delete-answer delete-icon">
                    <i class="fa fa-minus-circle" style="font-size:20px;color:red"></i>
                </div>
            </div>
        @endfor

        <div class="answer_cloneable answer-element form-group row" style="display: none;">
            <div class="form-group col-md-6">
                <input class='form-control answer_name' type='text' name='' value=''
                       placeholder='Answer'/>
            </div>

            <div class="form-group col-md-6">
                <input class='form-control arabic-input answer_name_ar' type='text' name='' value=''
                       placeholder='Answer(Arabic)'/>
            </div>

            <div class="delete-answer delete-icon">
                <i class="fa fa-minus-circle" style="font-size:20px;color:red"></i>
            </div>
        </div>

        <div class="answers_wrapper">

        </div>

        <div class="btn btn-success add-answer">
            <i class="fa fa-plus" aria-hidden="true"></i>
        </div>
    </div>

</div>
@endfor

<div class="question_cloneable question_wrapper" style="display: none;">
    <input type="hidden" id="question_index" value="{{$i}}" />

    <div class='form-group {{$class}}' style="">
        <div class="row">
            <div class="col-md-6">
                <label>Question</label>
                <input class='form-control question_input' type='text' name='' value='' placeholder='question' />
            </div>

            <div class="col-md-6 arabic-input">
                <label>Question (Arabic)</label>
                <input class='form-control question_input_ar' type='text' name='' value='' placeholder='question (arabic)' />
            </div>

        </div>
        <div class="delete-question">
            <i class="fa fa-minus-circle" style="font-size:20px;color:red"></i>
        </div>

    </div>

    <div class="form-group answers {{$class}}" style="padding:0 50px;">
        <label>Answers</label>

        <div class="answer_cloneable  answer-element form-group row" style="display: none;">

            <div class="form-group col-md-6">
                <input class='form-control answer_name' type='text' name='' value=''
                       placeholder='Answer'/>
            </div>

            <div class="form-group col-md-6">
                <input class='form-control arabic-input answer_name_ar' type='text' name='' value=''
                       placeholder='Answer(Arabic)'/>
            </div>
            <div class="delete-answer delete-icon">
                <i class="fa fa-minus-circle" style="font-size:20px;color:red"></i>
            </div>
        </div>

        <div class="answers_wrapper">

        </div>

        <div class="btn btn-success add-answer">
            <i class="fa fa-plus" aria-hidden="true"></i>
        </div>

    </div>
</div>

<div class="cloneable_wrapper">

</div>


<div class="col-md-12 add-question">
    <div class="btn btn-success">
        More questions
    </div>
</div>