@extends($layout)

@section('content')

    @foreach($boxes as $box)
        <div class="{{$box['wrapper-class']}}">
            <div class="box {{$box['class']}}">
                <div class="box-header with-border">
                    <h3 class="box-title">{{$box['box-header']}}</h3>

                    <!--
                    <div class="box-tools pull-right">
                        <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i></button>
                        <button type="button" class="btn btn-box-tool" data-widget="remove"><i class="fa fa-remove"></i></button>
                    </div>
                    -->
                </div>

                <div class="box-body">
                    <div>
                        @foreach($box['form_fields'] as $field)
                            {!!$field!!}
                        @endforeach
                    </div>
                    <!-- /.row -->
                </div>
            </div>

        </div>
    @endforeach


@endsection

@section('header_resources')
    <style>
        .show_image{
            width: 100px;
        }
    </style>
@endsection

@section('footer_resources')
    <script>
        $("input").prop("disabled", true);
    </script>
@endsection