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

    .option-text-flag{
        font-weight: normal;
        margin: 0 0 14px 4px;
        display: block;
    }

</style>


{{-- Poll options with a "member writes his own text" tick box per option (see the free text answers migration). --}}
<div class="form-group options {{$class}}" style="padding:0 50px;">

    <div class="option_cloneable  option-element form-group row" style="display: none;">

        <div class="form-group col-md-6">
            <input class='form-control option_name' type='text' name='' value=''
                   placeholder='English {{$label}}'/>
        </div>

        <div class="form-group col-md-6">
            <input class='form-control option_name_ar' type='text' name='' value=''
                   placeholder='Arabic {{$label}}' dir="rtl"/>
        </div>

        <div class="col-md-12">
            <label class="option-text-flag">
                <input type="checkbox" class="option_allows_text" name="" value="1">
                The member writes his own text on this option (up to 300 characters, only the CMS sees it)
            </label>
        </div>

        <div class="delete-option delete-icon">
            <i class="fa fa-minus-circle" style="font-size:20px;color:red"></i>
        </div>
    </div>

    <div class="options_wrapper">
        @if($default)
            @for($i=0; $i < count($default); $i++)
                <div class="option-element row">
                    <input type="hidden" name='options[{{$i}}][id]' value="{{$default[$i]['id']}}" />
                    <div class="form-group col-md-6">
                        <input class='form-control' type='text' name='options[{{$i}}][name]' value='{{$default[$i]['name']}}'
                               placeholder='English {{$label}}' style="margin-bottom:15px;"/>
                    </div>

                    <div class="form-group col-md-6">
                        <input class='form-control' type='text' name='options[{{$i}}][name_ar]' value='{{$default[$i]['name_ar']}}'
                               placeholder='Arabic {{$label}}' style="margin-bottom:15px;" dir="rtl"/>
                    </div>

                    <div class="col-md-12">
                        <label class="option-text-flag">
                            <input type="checkbox" name="options[{{$i}}][allows_text]" value="1" @if(!empty($default[$i]['allows_text'])) checked @endif>
                            The member writes his own text on this option (up to 300 characters, only the CMS sees it)
                        </label>
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
