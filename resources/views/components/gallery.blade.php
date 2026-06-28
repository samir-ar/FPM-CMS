@extends($layout)

@section('header_resources')
<style>
    .gallery-image{
        width: calc(25% - 10px );
        float: left;
        background-size: cover;
        background-position: 50%;
        margin: 0 5px;
        cursor: pointer;
        position: relative;
    }

    .gallery-image:before{
        content: '';
        display: block;
        padding-top: 100%;
    }

    .action-checkbox{
        position: absolute;
        top:10px;
        right: 10px;
    }
</style>
@endsection

@section('footer_resources')
    @if(isset($scripts))
        @foreach($scripts as $script)
            <script src="{{url($script)}}"></script>
        @endforeach
    @endif

    <script>
        $(document).ready(function(){
            $('.filter-option').on('change', function(){
                var value = $(this).val();
                var name = $(this).closest('.form-group').find('.filter_name').val();


                var url_string = window.location.href;
                var url = new URL(url_string);
                var c = url.searchParams.get(name);

                var query_string = url.search;
                var search_params = new URLSearchParams(query_string);

                //replace the parameter
                if(c){
                    search_params.set(name, value);
                }else{
                    search_params.append(name, value)
                }

                url.search = search_params.toString();
                window.location.replace(url);

            });


            var images_selected = [];

            $('.action-checkbox').change(function() {

                var id = $(this).val();

                if($(this).is(":checked")) {
                    images_selected.push(id);
                    $('.form_selected_images').val(JSON.stringify(images_selected));
                }
                else{
                    var index = images_selected.indexOf(id);
                    if (index > -1) {
                        images_selected.splice(index, 1);
                    }
                    $('.form_selected_images').val(JSON.stringify(images_selected));
                }

            });

        })
    </script>
@endsection

@section('content')
    <div class="row">
        @if(isset($boxes['gallery']))
            @php
                $box = $boxes['gallery'];

                //dd($box['images'])
            @endphp


            <div class="{{$box['wrapper-class']}}">
                <div class="box {{$box['class']}}">
                    <div class="box-header with-border">
                        <h3 class="box-title">{{isset($box['box-header']) ? $box['box-header'] : 'Gallery'}}</h3>

                        <div class="box-body">
                            @if(!$box['images']->first())
                                No images
                            @endif

                            @foreach($box['images'] as $image)
                                <div class="gallery-image" style="background-image: url('{{url($image->src)}}')">
                                    <input class="action-checkbox" value="{{$image->id}}" type="checkbox">
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        @endif

        @if(isset($boxes['side']))
            @php
                $side = $boxes['side'];
                $sub = $side['sub'];
            @endphp

            <div class="{{$side['wrapper-class']}}">
                @if(isset($sub['filter']))
                    @php
                        $filter = $sub['filter']
                    @endphp
                    <div class="col-md-12" style="padding: 0px;">
                        <div class="box {{$filter['class']}}">
                            <div class="box-header with-border">
                                <h3 class="box-title">{{isset($filter['box-header']) ? $filter['box-header'] : 'Filter'}}</h3>

                                <div class="box-body">

                                    @foreach($filter['filter_options'] as $option)
                                        <label>{{$option['label']}}</label>


                                        <div class="form-group">
                                            <input type="hidden" class="filter_name" value="{{$option['name']}}">
                                            <select class="form-control filter-option">
                                                <option value="all">All</option>
                                                @foreach($option['options'] as $k => $v)
                                                    <option value="{{$k}}" {{isset($option['default']) ? ($option['default'] == $k ? 'selected' : '') : ''}}>{{$v}}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                @if(isset($sub['selected_action']))
                    @php
                        $selected_actions = $sub['selected_action']
                    @endphp

                    <div class="col-md-12" style="padding: 0px">
                        <div class="box {{$selected_actions['class']}}">
                            <div class="box-header with-border">
                                <h3 class="box-title">{{isset($filter['box-header']) ? $filter['box-header'] : 'With Selected'}}</h3>

                                <div class="box-body">
                                    @foreach($selected_actions['actions'] as $a)
                                        <form action="{{$a['action']}}" class="action_form" id="{{$a['name']}}_form" method="POST">
                                            @csrf

                                            <input type="hidden" name="images" class="form_selected_images">
                                            {{$a['label']}} &nbsp;&nbsp; <a href="#" onclick="document.getElementById('{{$a['name'].'_form'}}').submit();">{!! $a['button'] !!}</a>
                                        </form>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                @if(isset($sub['form']))
                    <form class="form" action="{{$sub['form']['action']}}" method='POST' enctype="multipart/form-data">
                        @csrf
                        <div class="col-md-12" style="padding: 0px">
                            <div class="box box-danger">
                                <div class="box-header with-border">

                                    <h3 class="box-title">Uploader</h3>

                                    <div class="box-body">
                                        @foreach($sub['form']['form_fields'] as $field)
                                            {!! $field !!}
                                        @endforeach
                                    </div>

                                    <div class="col-md-12">
                                        <button type="submit" class="btn btn-primary float-right">Submit</button>
                                    </div>
                                </div>


                            </div>


                        </div>



                    </form>

                @endif
            </div>
        @endif
    </div>
@endsection




