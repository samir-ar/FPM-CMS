<style>

    .delete-option{
        position: absolute;
        top: 7px;
        left: -24px;
        cursor: pointer;
    }

    .option-element{
        position: relative;
    }

    .add-option{
        float: right;
    }

</style>


<div class="form-group options {{$class}}" style="padding:0 50px;">

    <div class="option_cloneable  option-element form-group row" style="display: none;">

        <div class="form-group col-md-12">
            <input class='form-control option_name_ar' type='text' name='' value=''
                   placeholder='Arabic {{$label}}' dir="rtl"/>
        </div>

        {{--
        <div class="form-group col-md-6">
            <input class='form-control arabic-input answer_name_ar' type='text' name='' value=''
                   placeholder='Answer(Arabic)'/>
        </div>
        --}}
        <div class="delete-option delete-icon">
            <i class="fa fa-minus-circle" style="font-size:20px;color:red"></i>
        </div>
    </div>

    <div class="options_wrapper">
        @if($default)
            @for($i=0; $i < count($default); $i++)
                <div class="option-element row">
                    <input type="hidden" name='options[{{$i}}][id]' value="{{$default[$i]['id']}}" />
                    <div class="form-group col-md-12">
                        <input class='form-control' type='text' name='options[{{$i}}][name_ar]' value='{{$default[$i]['name_ar']}}'
                               placeholder='Arabic {{$label}}' style="margin-bottom:15px;" dir="rtl"/>
                    </div>
                    <div class="delete-option delete-icon">
                        <i class="fa fa-minus-circle" style="font-size:20px;color:red"></i>
                    </div>

                </div>
            @endfor
        @endif
    </div>

    <div class="btn btn-success add-option">
        <i class="fa fa-plus" aria-hidden="true"></i>
    </div>

</div>



