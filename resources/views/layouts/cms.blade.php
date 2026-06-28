<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <title>{{env('APP_NAME'), 'CMS'}}</title>
    <!-- Tell the browser to be responsive to screen width -->
    <meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">
    <!-- Bootstrap 3.3.7 -->
    <link rel="stylesheet" href="{{url('bower_components/bootstrap/dist/css/bootstrap.min.css')}}">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="{{url('bower_components/font-awesome/css/font-awesome.min.css')}}">
    <!-- Ionicons -->
    <link rel="stylesheet" href="{{url('bower_components/Ionicons/css/ionicons.min.css')}}">
    <!-- Theme style -->
    <link rel="stylesheet" href="{{url('cms/css/AdminLTE.min.css')}}">
    <!-- AdminLTE Skins. Choose a skin from the css/skins
         folder instead of downloading all of them to reduce the load. -->
    <link rel="stylesheet" href="{{url('cms/css/skins/_all-skins.min.css')}}">
    <!-- Morris chart -->
    <link rel="stylesheet" href="{{url('bower_components/morris.js/morris.css')}}">
    <!-- jvectormap -->
    <link rel="stylesheet" href="{{url('bower_components/jvectormap/jquery-jvectormap.css')}}">
    <!-- Date Picker -->
    <link rel="stylesheet" href="{{url('bower_components/bootstrap-datepicker/dist/css/bootstrap-datepicker.min.css')}}">
    <!-- Daterange picker -->
    <link rel="stylesheet" href="{{url('bower_components/bootstrap-daterangepicker/daterangepicker.css')}}">

    <link rel="stylesheet" href="{{url('bower_components/select2/dist/css/select2.min.css')}}">

    <!-- bootstrap wysihtml5 - text editor -->
    <link rel="stylesheet" href="{{url('plugins/bootstrap-wysihtml5/bootstrap3-wysihtml5.min.css')}}">

    <link rel="stylesheet" href="{{url('bower_components/datatables.net-bs/css/dataTables.bootstrap.min.css')}}">

    <link rel="stylesheet" href="{{url('bower_components/jquery-upload-image/style.css')}}">


    <link rel="stylesheet" href="{{url('css/cms.css')}}">
    <link rel="stylesheet" href="{{url('css/jquery.toast.min.css')}}">


    <!-- HTML5 Shim and Respond.js IE8 support of HTML5 elements and media queries -->
    <!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
    <!--[if lt IE 9]>
    <script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
    <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
    <![endif]-->


    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datetimepicker/4.7.14/css/bootstrap-datetimepicker.min.css">

    <!-- Google Font -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,600,700,300italic,400italic,600italic">

    @yield('header_resources')
</head>
<body class="hold-transition skin-blue sidebar-mini">
<div class="wrapper">
    <header class="main-header">
        <!-- Logo -->

        <a href="#" class="logo">
            <!-- mini logo for sidebar mini 50x50 pixels -->
            <span class="logo-mini"><b>{{env('APP_NAME'), 'CMS'}}</b></span>
            <!-- logo for regular state and mobile devices -->
            <span class="logo-lg"><b>{{env('APP_NAME'), 'CMS'}}</b></span>
        </a>
        <!-- Header Navbar: style can be found in header.less -->
        <nav class="navbar navbar-static-top">
            <!-- Sidebar toggle button-->
            <a href="#" class="sidebar-toggle" data-toggle="push-menu" role="button">
                <span class="sr-only">Toggle navigation</span>
            </a>

            <div class="navbar-custom-menu">
                <ul class="nav navbar-nav">
                    <li class="dropdown user user-menu">
                        @php
                            $user = Auth::guard('admin')->user();
                        @endphp

                        <a href="#" class="dropdown-toggle" data-toggle="dropdown">
                            <img src="{{$user->avatar ? : url('images/default_avatar.png')}}" class="user-image" alt="User Image">
                            <span class="hidden-xs">{{$user->name}}</span>
                        </a>
                        <ul class="dropdown-menu">
                            <!-- User image -->
                            <li class="user-header">
                                <img src="{{$user->avatar ?  : url('images/default_avatar.png')}}" class="img-circle" alt="User Image">

                                <p>
                                    @if($user->description)
                                        Alexander Pierce - Web Developer
                                        <small>Member since Nov. 2012</small>
                                    @endif
                                </p>
                            </li>
                            <!-- Menu Footer-->
                            <li class="user-footer">
                                <!--
                                <div class="pull-left">
                                    <a href="{{route('admin.profile.edit', $user->id )}}" class="btn btn-default btn-flat">Profile</a>
                                </div>
                                -->


                                <div class="pull-right">
                                    <a href="{{route('admin.logout')}}" class="btn btn-default btn-flat">Sign out</a>
                                </div>
                            </li>
                        </ul>
                    </li>

                </ul>
            </div>
        </nav>
    </header>
    <!-- Left side column. contains the logo and sidebar -->
    <aside class="main-sidebar">
        <!-- sidebar: style can be found in sidebar.less -->
        <section class="sidebar">
            <!-- Sidebar user panel -->
            <div class="user-panel">

                <div class="pull-left image">
                    <img src="{{$user->avatar ?  : url('images/default_avatar.png')}}" class="img-circle" alt="User Image">
                </div>

                <div class="pull-left info">
                    <p>{{isset($user) ? $user->name : ''}}</p>
                    <a href="#"><i class="fa fa-circle text-success"></i> Online</a>
                </div>
            </div>
            <!-- /.search form -->
            <!-- sidebar menu: : style can be found in sidebar.less -->
            <ul class="sidebar-menu" data-widget="tree">
                <li class="header">MAIN NAVIGATION</li>

                @php
                    $pages= \App\Page::where('parent_id', null)->get();
                @endphp

                @foreach($pages as $page)
                    @if($user->hasPage($page->id))
                        @php
                            $active = false;

                            if($page->is_parent || $page->hasChilds()){

                                $page->children()->get()->each(function($q) use(&$active){
                                    if($q->url == Route::currentRouteName())
                                        $active = true;

                                    else if(strpos($q->underline, Route::currentRouteName()) !== false)
                                        $active = true;
                                });
                            }

                            if($page->url == Route::currentRouteName() || strpos($page->underline, Route::currentRouteName()) !== false){
                                $active = true;
                            }

                        @endphp

                        <li class="{{$page->is_parent ? 'treeview' : ''}} {{$active ? 'active' : ''}}">
                            <a href="{{$page->url ? route($page->url) : '#'}}">
                                <i class="{{$page->logo}}" aria-hidden="true"></i>
                                <span>{!! $page->name !!}</span>

                                @if($page->is_parent)
                                    <span class="pull-right-container">
                                        <i class="fa fa-angle-left pull-right"></i>
                                    </span>
                                @endif
                            </a>

                            @if($page->is_parent)
                                @php
                                    $children = \App\Page::where('parent_id', $page->id)->get();
                                @endphp

                                <ul class="treeview-menu">
                                    @foreach($children as $child)
                                        @if(!$child->hide)
                                            @php
                                                $active = false;
                                                if(Route::currentRouteName() == $child->url)
                                                    $active = true;

                                                else if(strpos($child->underline, Route::currentRouteName()) !== false)
                                                    $active = true;
                                            @endphp

                                            <li class="{{ $active ? 'active' : '' }}"><a href="{{route($child->url).$child->parameters}}"><i class="fa fa-circle-o"></i>{!! $child->name !!}</a></li>
                                        @endif
                                    @endforeach
                                </ul>
                            @endif
                        </li>
                    @endif
                @endforeach
            </ul>


        </section>
        <!-- /.sidebar -->
    </aside>

    <!-- Content Wrapper. Contains page content -->
    <div class="content-wrapper">
        <!-- Content Header (Page header) -->
        <section class="content-header">
            <h1>
                {{isset($pageTitle) ? $pageTitle : 'Admin'}}
            </h1>
        </section>

        <!-- Main content -->
        <section class="content">


        @if ($errors->any())
                <div class="callout callout-danger col-md-12">
                    <h4>Warning!</h4>

                    <p>{{ implode('', $errors->all(    )) }}</p>
                </div>
            @endif

            @if(session()->has('message'))
                <div class="callout callout-success col-md-12">
                    {{ session()->get('message') }}
                </div>
            @endif

            @yield('content')
        </section>
        <!-- /.content -->
    </div>
    <!-- /.content-wrapper -->
    <footer class="main-footer">
        <div class="pull-right hidden-xs">
        </div>
        <strong>Designed by <a href="https://tedmob.com"><b>TEDMOB.com</b></a></strong>
    </footer>

    <!-- Control Sidebar -->


    <div class="modal" id="deleteModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Delete</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>

                <form class="delete" action="#" method="post">
                    <input type="hidden" name="_method" value="DELETE">

                    {{csrf_field()}}

                    <div class="modal-body">
                        Are you sure you want to delete?
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Delete</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</div>
<!-- ./wrapper -->

<!-- jQuery 3 -->
<script src="{{url('bower_components/jquery/dist/jquery.min.js')}}"></script>
<!-- jQuery UI 1.11.4 -->
<script src="{{url('bower_components/jquery-ui/jquery-ui.min.js')}}"></script>
<!-- Resolve conflict in jQuery UI tooltip with Bootstrap tooltip -->
<script>
    $.widget.bridge('uibutton', $.ui.button);
</script>
<!-- Bootstrap 3.3.7 -->
<script src="{{url('bower_components/bootstrap/dist/js/bootstrap.min.js')}}"></script>
<!-- Morris.js charts -->
<script src="{{url('bower_components/raphael/raphael.min.js')}}"></script>
<script src="{{url('bower_components/morris.js/morris.min.js')}}"></script>
<!-- Sparkline -->
<script src="{{url('bower_components/jquery-sparkline/dist/jquery.sparkline.min.js')}}"></script>
<!-- jvectormap -->
<script src="{{url('plugins/jvectormap/jquery-jvectormap-1.2.2.min.js')}}"></script>
<script src="{{url('plugins/jvectormap/jquery-jvectormap-world-mill-en.js')}}"></script>
<!-- jQuery Knob Chart -->
<script src="{{url('bower_components/jquery-knob/dist/jquery.knob.min.js')}}"></script>
<!-- daterangepicker -->
<script src="{{url('bower_components/moment/min/moment.min.js')}}"></script>
<script src="{{url('bower_components/bootstrap-daterangepicker/daterangepicker.js')}}"></script>
<!-- datepicker -->
<script src="{{url('bower_components/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js')}}"></script>
<!-- Bootstrap WYSIHTML5 -->
<script src="{{url('plugins/bootstrap-wysihtml5/bootstrap3-wysihtml5.all.min.js')}}"></script>
<!-- Slimscroll -->
<script src="{{url('bower_components/jquery-slimscroll/jquery.slimscroll.min.js')}}"></script>
<!-- FastClick -->
<script src="{{url('bower_components/fastclick/lib/fastclick.js')}}"></script>
<!-- AdminLTE App -->
<script src="{{url('cms/js/adminlte.min.js')}}"></script>
<!-- AdminLTE for demo purposes -->
<script src="{{url('cms/js/demo.js')}}"></script>
<script src="{{url('bower_components/select2/dist/js/select2.full.min.js')}}"></script>
<!-- iCheck 1.0.1 -->
<script src="{{url('plugins/iCheck/icheck.min.js')}}"></script>
<!-- bootstrap time picker -->
<script src="{{url('plugins/timepicker/bootstrap-timepicker.min.js')}}"></script>
<script src="{{url('bower_components/ckeditor/ckeditor.js')}}"></script>


<script src="{{url('bower_components/datatables.net/js/jquery.dataTables.min.js')}}"></script>
<script src="{{url('bower_components/datatables.net-bs/js/dataTables.bootstrap.min.js')}}"></script>

<script src="{{url('bower_components/jquery-upload-image/script.js')}}"></script>
<script src="{{url('js/image_ajax_deleter.js')}}"></script>
<script src="{{url('js/pdf_ajax_deleter.js')}}"></script>
<script src="{{url('js/media.js?v=2')}}"></script>
<script src="{{url('js/jquery.toast.min.js')}}"></script>


<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datetimepicker/4.7.14/js/bootstrap-datetimepicker.min.js"></script>


<script src="{{url('js/editable_table.js')}}"></script>




<!-- Page script -->
<script>
    $(function () {
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': '{{csrf_token()}}',
            }
        });


        $('.__myfileuploader').each(function() {

            var url = $(this).closest('.form-group').find('.myfileuploader__url').val();
            var deleteUrl = $(this).closest('.form-group').find('.myfileDelete__url').val();

            var ele = $(this).closest('.form-group');

            $(this).uploadFile({
                url:url,
                fileName:"myfile",
                showDelete: true,
                acceptFiles:"image/*",
                onSuccess: function(files, data){
                    console.log(data);
                    var val = ele.find('.myFiles').val();

                    if(!val){
                        var object = []
                    }
                    else object = JSON.parse(val)
                    object.push(data);

                    ele.find('.myFiles').val(JSON.stringify(object));
                },
                deleteCallback: function(data){
                    $.post(deleteUrl, {name: data},
                        function (resp,textStatus, jqXHR) {

                            //remove data from array
                            var val = ele.find('.myFiles').val();
                            object = JSON.parse(val);
                            var index = object.indexOf(resp);
                            if (index > -1) {
                                object.splice(index, 1);
                            }

                            ele.find('.myFiles').val(JSON.stringify(object));

                        });
                }

            });
        });


        //Initialize Select2 Elements
        $('.select2').not('font-awesome').select2();

        function formatText (icon) {
            return $('<span><i class="fa ' + $(icon.element).data('icon') + '"></i> ' + icon.text + '</span>');
        };

        $('.select2.font-awesome').select2({
            templateSelection: formatText,
            templateResult: formatText
        });

        //Date range picker
        if($('#reservation').length){
            $('#reservation').daterangepicker()
        }

        //Date range picker with time picker
        if(('#reservationtime').length){
            $('#reservationtime').daterangepicker({ timePicker: true, timePickerIncrement: 30, format: 'MM/DD/YYYY h:mm A' })
        }

        //Date range as a button
        if($('#daterange-btn').length){
            $('#daterange-btn').daterangepicker(
                {
                    ranges   : {
                        'Today'       : [moment(), moment()],
                        'Yesterday'   : [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
                        'Last 7 Days' : [moment().subtract(6, 'days'), moment()],
                        'Last 30 Days': [moment().subtract(29, 'days'), moment()],
                        'This Month'  : [moment().startOf('month'), moment().endOf('month')],
                        'Last Month'  : [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
                    },
                    startDate: moment().subtract(29, 'days'),
                    endDate  : moment()
                },
                function (start, end) {
                    $('#daterange-btn span').html(start.format('MMMM D, YYYY') + ' - ' + end.format('MMMM D, YYYY'))
                }
            )
        }
        //Date picker
        $('.datepicker').datepicker({
            autoclose: true,
            format: 'yyyy-mm-dd',
            Default: 'today',

        });






        $('.daterange-btn').daterangepicker(
            {
                ranges   : {
                    'Today'       : [moment(), moment()],
                    'Yesterday'   : [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
                    'Last 7 Days' : [moment().subtract(6, 'days'), moment()],
                    'Last 30 Days': [moment().subtract(29, 'days'), moment()],
                    'This Month'  : [moment().startOf('month'), moment().endOf('month')],
                    'Last Month'  : [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
                },
                startDate: moment().subtract(29, 'days'),
                endDate  : moment()
            },
            function (start, end) {
                $('.daterange-btn span').html(start.format('MMMM D, YYYY') + ' - ' + end.format('MMMM D, YYYY'));
                $('.daterange-btn .from_date').val(start.format('YYYY-MM-DD'));
                $('.daterange-btn .to_date').val(end.format('YYYY-MM-DD'));
            }
        );


        if($('.dateTimePickerSingle').length){
            $('.dateTimePickerSingle').each(function(){
                $(this).datetimepicker({
                    format: 'YYYY-MM-DD hh:mm A',
                });
            });
        }

        //Timepicker
        $('.timepicker').timepicker({
            showInputs: false
        });

        $('.upload-img-input-file').change(function(){
            var $img = $(this).closest('.form-group').find('img');

            readURL(this, $img);
        });

        function readURL(input, image){

            if(input.files && input.files[0]){
                var reader = new FileReader();

                reader.onload = function(e){
                    image.attr('src', e.target.result);
                }

                reader.readAsDataURL(input.files[0]);
            }
        }

        if($('.editor').length){
            $('.editor').not(function(){return $(this).parents('.no-ck').length}).each(function(){
                CKEDITOR.replace($(this).attr('id'), {
                    extraPlugins : 'bidi'

                });
            });
        }


        if($('.dateTimePicker').length){
            $('.dateTimePicker').each(function(){
                var ele = $(this);
                $(this).daterangepicker(
                    {
                        timePicker: true,
                        locale: {
                            format: 'YYYY-MM-DD HH:mm',
                        },
                    },
                    function(start, end){
                        var container = ele.closest('.form-group');
                        ele.find('span').html(start.format('YYYY-MM-DD HH:mm') + ' - ' + end.format('YYYY-MM-DD HH:mm'));
                        container.find('.from_date').val(start.format('YYYY-MM-DD HH:mm') );
                        container.find('.to_date').val(end.format('YYYY-MM-DD HH:mm'));
                    }
                );
            });
        }

        /*
           *   Options fields Action
           *
        */
        $(document).on('click', '.add-option', function(){
            var wrapper = $(this).closest('.options');
            var answer = wrapper.find('.option_cloneable').clone();
            answer.removeClass('option_cloneable');

            var index = wrapper.find('.option-element').not('.option_cloneable').length;

            answer.find('.option_name').attr('name', 'options['+ index +'][name]');
            answer.find('.option_name_ar').attr('name', 'options['+ index +'][name_ar]');

            answer.show();

            wrapper.find('.options_wrapper').append(answer);
        });

        $(document).on('click', '.delete-option', function() {
            $(this).closest('.option-element').remove();
        });

        /*
            *   Questions answers fields Action
            *
         */

        $(document).on('click', '.add-answer', function(){
            var wrapper = $(this).closest('.answers');
            var answer = wrapper.find('.answer_cloneable').clone();
            answer.removeClass('answer_cloneable');

            var index = wrapper.find('.answer-element').not('.answer_cloneable').length;
            var question_index = $(this).closest('.question_wrapper').find('#question_index').val();

            answer.find('.answer_name').attr('name', 'questions['+ question_index +'][answers]['+ index +'][name]');
            answer.find('.answer_name_ar').attr('name', 'questions['+ question_index +'][answers]['+ index +'][name_ar]');
            answer.show();


            wrapper.find('.answers_wrapper').append(answer);

        });


        $(document).on('click', '.add-question', function(){
            var question = $('.question_cloneable').clone();
            question.removeClass('question_cloneable');

            //count questions
            var index = $('.question_wrapper').not('.question_cloneable').length;

            question.find('#question_index').val(index);
            question.find('.question_input').attr('name', 'questions['+ index +'][name]');
            question.find('.question_input_ar').attr('name', 'questions['+ index +'][name_ar]');

            $('#questions_index').val(index);

            question.show();

            $('.cloneable_wrapper').append(question)

            question.find('.add-answer').trigger('click');
        });


        $(document).on('click', '.delete-question', function(){
            $(this).closest('.question_wrapper').remove();
        });


        $(document).on('click', '.delete-answer', function() {
            $(this).closest('.answer-element').remove();
        });


        $('#cloneable_fields_btn').click(function(){
            var $field = $('.cloneable_field').clone();
            $field.removeClass('cloneable_field');

            $field.find('.select2-container').remove();
            $field.find('.select2').select2({
                templateSelection: formatText,
                templateResult: formatText
            });

            $(this).closest('.cloneable_fields_container').find('.cloneable_fields_container_inner').append($field);
        });

    })
</script>
@yield('footer_resources')
</body>
</html>
